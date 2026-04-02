import Extend from "flarum/common/extenders";
import app from "flarum/forum/app";
import { extend as flarumExtend, override } from "flarum/common/extend";
import User from "flarum/common/models/User";
import HeaderSecondary from "flarum/forum/components/HeaderSecondary";
import UserPage from "flarum/forum/components/UserPage";
import PostUser from "flarum/forum/components/PostUser";
import CommentPost from "flarum/forum/components/CommentPost";
import LinkButton from "flarum/common/components/LinkButton";

import Collectible from "./forum/models/Collectible";
import BlindBox from "./forum/models/BlindBox";
import CheckinRecord from "./forum/models/CheckinRecord";
import BarterProposal from "./forum/models/BarterProposal";
import BarterProposalItem from "./forum/models/BarterProposalItem";

import CheckinButton from "./forum/components/CheckinButton";
import PostCollectibleBadge from "./forum/components/PostCollectibleBadge";
import PostCollectibleShowcase from "./forum/components/PostCollectibleShowcase";
import BlindBoxOpener from "./forum/components/BlindBoxOpener";
import UserCollectiblesPage from "./forum/components/UserCollectiblesPage";
import UserBlindBoxesPage from "./forum/components/UserBlindBoxesPage";
import BarterThreadPanel from "./forum/components/BarterThreadPanel";
import BarterComposerPanel from "./forum/components/BarterComposerPanel";

import { connect as wsConnect, subscribe } from "./forum/utils/notifications";
import {
  createBarterProposalFromComposer,
  ensureBarterComposerFields,
  hasBarterDraft,
  loadBarterAssets,
  validateBarterComposer,
} from "./forum/utils/barterComposer";

export const extend = [
  new Extend.Store()
    .add("blindboxes", BlindBox)
    .add("barter-proposal-items", BarterProposalItem)
    .add("barter-proposals", BarterProposal)
    .add("collectibles", Collectible)
    .add("checkin-records", CheckinRecord),

  new Extend.Routes().add(
    "user.blindboxes",
    "/u/:username/blindboxes",
    UserBlindBoxesPage
  ),

  new Extend.Routes().add(
    "user.collectibles",
    "/u/:username/collectibles",
    UserCollectiblesPage
  ),

  new Extend.Model(User)
    .attribute<number>("blindBoxCount")
    .attribute<boolean>("canCheckin")
    .attribute<boolean>("hasCheckedInToday")
    .attribute<string>("lastCheckinAt")
    .attribute<number>("showcaseCollectibleId")
    .attribute<string>("showcaseCollectibleName")
    .attribute<string>("showcaseCollectibleCid")
    .attribute<string>("showcaseCollectibleRarity")
    .attribute<number>("showcaseCollectibleTokenId")
    .attribute<string>("web3Address")
    .attribute<string>("web3AccountId"),
];

app.initializers.add("donk-aigc-collectibles", () => {
  override("flarum/common/components/TextEditor", "onbuild", function (this: any, original: () => void) {
    const container = this.$?.(".TextEditor-editorContainer")?.[0];

    if (container) {
      this.__collectiblesEditorBuildRetries = 0;
      return original();
    }

    const retries = (this.__collectiblesEditorBuildRetries || 0) + 1;
    this.__collectiblesEditorBuildRetries = retries;

    if (retries > 20) {
      console.error("[donk-aigc-collectibles] TextEditor container was not ready during onbuild.");
      return;
    }

    window.setTimeout(() => {
      if (this.element?.isConnected && !this.attrs?.composer?.editor) {
        this.onbuild();
      }
    }, 50);
  });

  // Add check-in button to header
  flarumExtend(HeaderSecondary.prototype, "items", function (items: any) {
    if (app.session?.user) {
      items.add("donk-aigc-collectibles-checkin", <CheckinButton />, 15);

      // Blind box opener button
      items.add("donk-aigc-collectibles-blindbox", (
        <LinkButton
          className="Button Button--link BlindBoxInventory-trigger"
          href={app.route("user.blindboxes", { username: app.session.user.slug() })}
          title={app.translator.trans(
            "donk-aigc-collectibles.forum.blind_box.inventory_title"
          )}
        >
          <i className="fas fa-box-open" />
        </LinkButton>
      ), 14);
    }
  });

  // Add collectible badge next to post author
  flarumExtend(PostUser.prototype, "view", function (vnode: any) {
    const post = this?.attrs?.post;
    if (!vnode || !post || typeof post.user !== "function") return;

    const user = post.user();
    if (!user || typeof user.attribute !== "function") return;

    const showcaseId = user.attribute("showcaseCollectibleId");
    if (!showcaseId) return;

    if (!vnode.children) {
      vnode.children = [];
    }

    if (Array.isArray(vnode.children)) {
      vnode.children.push(<PostCollectibleBadge user={user} />);
    }
  });

  flarumExtend(CommentPost.prototype, "content", function (content: any[]) {
    const post = this?.attrs?.post;
    const user = post?.user?.();

    if (
      !user ||
      post?.isHidden?.() ||
      this?.isEditing?.() ||
      !user.attribute?.("showcaseCollectibleId") ||
      !user.attribute?.("showcaseCollectibleCid")
    ) {
      return;
    }

    const originalContent = content.slice();
    if (originalContent.length === 0) {
      return;
    }

    content.splice(0, content.length, (
      <div className="CollectibleShowcasePost">
        <div className="CollectibleShowcasePost-content">{originalContent}</div>
        <aside className="CollectibleShowcasePost-panel">
          <PostCollectibleShowcase user={user} />
        </aside>
      </div>
    ));
  });

  // Add collectibles tab to user profile
  flarumExtend(UserPage.prototype, "navItems", function (items: any) {
    const profileUser = this?.user ?? this?.attrs?.user;
    if (!profileUser) return;

    items.add(
      "blindboxes",
      <LinkButton
        href={app.route("user.blindboxes", { username: profileUser.slug() })}
        icon="fas fa-box-open"
      >
        {app.translator.trans(
          "donk-aigc-collectibles.forum.user.blindboxes_link"
        )}
      </LinkButton>,
      49
    );

    items.add(
      "collectibles",
      <LinkButton
        href={app.route("user.collectibles", { username: profileUser.slug() })}
        icon="fas fa-gem"
      >
        {app.translator.trans(
          "donk-aigc-collectibles.forum.user.collectibles_link"
        )}
      </LinkButton>,
      50
    );
  });

  // Connect WebSocket for real-time notifications
  if (app.session?.user) {
    wsConnect();
  }

  override("ext:flarum/messages/forum/components/DialogSection", "view", function (this: any, original: () => any) {
    const vnode = original();
    const dialog = this?.attrs?.dialog;

    if (!vnode || !dialog) {
      return vnode;
    }

    const stream = vnode.children?.[1];

    if (!stream) {
      return vnode;
    }

    vnode.children[1] = (
      <div className="DialogSection-streamWrap">
        {stream}
        <BarterThreadPanel dialog={dialog} />
      </div>
    );

    return vnode;
  });

  flarumExtend("ext:flarum/messages/forum/components/MessageComposer", "oninit", function (this: any, _value: unknown, vnode: any) {
    ensureBarterComposerFields(this.composer);

    const dialog = vnode.attrs?.replyingTo;

    if (dialog?.id?.()) {
      void loadBarterAssets(this.composer, dialog);
    }
  });

  flarumExtend("ext:flarum/messages/forum/components/MessageComposer", "headerItems", function (this: any, items: any) {
    const dialog = this.attrs?.replyingTo;

    if (!dialog?.id?.()) {
      return;
    }

    items.add(
      "donk-aigc-collectibles-barter-composer",
      <BarterComposerPanel composer={this.composer} dialog={dialog} />,
      90
    );
  });

  override("ext:flarum/messages/forum/components/MessageComposer", "hasChanges", function (this: any, original: () => boolean) {
    return original() || hasBarterDraft(this.composer);
  });

  override("ext:flarum/messages/forum/components/MessageComposer", "onsubmit", function (this: any, original: () => void) {
    const fields = ensureBarterComposerFields(this.composer);

    if (!fields.barterEnabled()) {
      return original();
    }

    const validationError = validateBarterComposer(this.composer);

    if (validationError) {
      m.redraw();
      return;
    }

    this.loading = true;
    fields.barterError(null);
    fields.barterValidationError(null);

    const data = this.data();

    app.store
      .createRecord("dialog-messages")
      .save(data, {
        params: {
          include: ["dialog"],
        },
      })
      .then(async (message: any) => {
        const dialog = this.attrs.replyingTo || message.dialog?.() || this.attrs.replyingTo;

        try {
          await createBarterProposalFromComposer(dialog, this.composer);
        } catch (error: any) {
          app.alerts.show(
            { type: "error" },
            error?.response?.errors?.[0]?.detail ||
              (app.translator.trans("donk-aigc-collectibles.forum.barter.action_failed") as string)
          );
        }

        this.composer.hide();
        m.route.set(app.route("dialog", { id: message.data.relationships!.dialog.data.id }));
        this.attrs.onsubmit?.(message);
      })
      .catch((error: any) => {
        fields.barterError(
          error?.response?.errors?.[0]?.detail ||
            (app.translator.trans("donk-aigc-collectibles.forum.barter.action_failed") as string)
        );
      })
      .finally(() => {
        this.loaded();
      });
  });
});
