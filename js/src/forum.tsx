import Extend from "flarum/common/extenders";
import app from "flarum/forum/app";
import { extend as flarumExtend } from "flarum/common/extend";
import User from "flarum/common/models/User";
import HeaderSecondary from "flarum/forum/components/HeaderSecondary";
import UserPage from "flarum/forum/components/UserPage";
import PostUser from "flarum/forum/components/PostUser";
import LinkButton from "flarum/common/components/LinkButton";

import Collectible from "./forum/models/Collectible";
import Trade from "./forum/models/Trade";
import CheckinRecord from "./forum/models/CheckinRecord";

import CheckinButton from "./forum/components/CheckinButton";
import PostCollectibleBadge from "./forum/components/PostCollectibleBadge";
import BlindBoxOpener from "./forum/components/BlindBoxOpener";
import UserCollectiblesPage from "./forum/components/UserCollectiblesPage";

import { connect as wsConnect, subscribe } from "./forum/utils/notifications";

export const extend = [
  new Extend.Store()
    .add("collectibles", Collectible)
    .add("trades", Trade)
    .add("checkin-records", CheckinRecord),

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
    .attribute<string>("web3Address")
    .attribute<string>("web3AccountId"),
];

app.initializers.add("donk-aigc-collectibles", () => {
  // Add check-in button to header
  flarumExtend(HeaderSecondary.prototype, "items", function (items: any) {
    if (app.session?.user) {
      items.add("donk-aigc-collectibles-checkin", <CheckinButton />, 15);

      // Blind box opener button
      items.add(
        "donk-aigc-collectibles-blindbox",
        <button
          className="Button Button--link BlindBoxOpener-trigger"
          onclick={() => app.modal.show(BlindBoxOpener)}
          title={app.translator.trans(
            "donk-aigc-collectibles.forum.blind_box.open_title"
          )}
        >
          <i className="fas fa-box-open" />
        </button>,
        14
      );
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

  // Add collectibles tab to user profile
  flarumExtend(UserPage.prototype, "navItems", function (items: any) {
    const profileUser = this?.user ?? this?.attrs?.user;
    if (!profileUser) return;

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

    subscribe("trade.created", () => {
      app.alerts.show(
        { type: "info" },
        app.translator.trans(
          "donk-aigc-collectibles.forum.trade.notification_received"
        )
      );
    });

    subscribe("trade.accepted", () => {
      app.alerts.show(
        { type: "success" },
        app.translator.trans(
          "donk-aigc-collectibles.forum.trade.notification_accepted"
        )
      );
    });

    subscribe("trade.rejected", () => {
      app.alerts.show(
        { type: "info" },
        app.translator.trans(
          "donk-aigc-collectibles.forum.trade.notification_rejected"
        )
      );
    });
  }
});
