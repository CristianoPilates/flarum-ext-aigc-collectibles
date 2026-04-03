import app from 'flarum/forum/app';
import { extend as flarumExtend, override } from 'flarum/common/extend';
import HeaderSecondary from 'flarum/forum/components/HeaderSecondary';
import UserPage from 'flarum/forum/components/UserPage';
import PostUser from 'flarum/forum/components/PostUser';
import CommentPost from 'flarum/forum/components/CommentPost';
import LinkButton from 'flarum/common/components/LinkButton';
import CheckinButton from '../components/CheckinButton';
import PostCollectibleBadge from '../components/PostCollectibleBadge';
import PostCollectibleShowcase from '../components/PostCollectibleShowcase';

export function installForumChrome(): void {
  installTextEditorBuildRetry();
  installHeaderButtons();
  installPostAuthorBadge();
  installPostShowcasePanel();
  installUserProfileLinks();
}

function installTextEditorBuildRetry(): void {
  override('flarum/common/components/TextEditor', 'onbuild', function (this: any, original: () => void) {
    const container = this.$?.('.TextEditor-editorContainer')?.[0];

    if (container) {
      this.__collectiblesEditorBuildRetries = 0;
      return original();
    }

    const retries = (this.__collectiblesEditorBuildRetries || 0) + 1;
    this.__collectiblesEditorBuildRetries = retries;

    if (retries > 20) {
      console.error('[donk-aigc-collectibles] TextEditor container was not ready during onbuild.');
      return;
    }

    window.setTimeout(() => {
      if (this.element?.isConnected && !this.attrs?.composer?.editor) {
        this.onbuild();
      }
    }, 50);
  });
}

function installHeaderButtons(): void {
  flarumExtend(HeaderSecondary.prototype, 'items', function (items: any) {
    if (!app.session?.user) {
      return;
    }

    items.add('donk-aigc-collectibles-checkin', <CheckinButton />, 15);

    items.add(
      'donk-aigc-collectibles-blindbox',
      <LinkButton
        className="Button Button--link BlindBoxInventory-trigger"
        href={app.route('user.blindboxes', { username: app.session.user.slug() })}
        title={app.translator.trans('donk-aigc-collectibles.forum.blind_box.inventory_title')}
      >
        <i className="fas fa-box-open" />
      </LinkButton>,
      14
    );
  });
}

function installPostAuthorBadge(): void {
  flarumExtend(PostUser.prototype, 'view', function (vnode: any) {
    const post = this?.attrs?.post;
    if (!vnode || !post || typeof post.user !== 'function') return;

    const user = post.user();
    if (!user || typeof user.attribute !== 'function') return;

    if (!user.attribute('showcaseCollectibleId')) return;

    if (!vnode.children) {
      vnode.children = [];
    }

    if (Array.isArray(vnode.children)) {
      vnode.children.push(<PostCollectibleBadge user={user} />);
    }
  });
}

function installPostShowcasePanel(): void {
  flarumExtend(CommentPost.prototype, 'content', function (content: any[]) {
    const post = this?.attrs?.post;
    const user = post?.user?.();

    if (
      !user ||
      post?.isHidden?.() ||
      this?.isEditing?.() ||
      !user.attribute?.('showcaseCollectibleId') ||
      !user.attribute?.('showcaseCollectibleCid')
    ) {
      return;
    }

    const originalContent = content.slice();
    if (originalContent.length === 0) {
      return;
    }

    content.splice(
      0,
      content.length,
      <div className="CollectibleShowcasePost">
        <div className="CollectibleShowcasePost-content">{originalContent}</div>
        <aside className="CollectibleShowcasePost-panel">
          <PostCollectibleShowcase user={user} />
        </aside>
      </div>
    );
  });
}

function installUserProfileLinks(): void {
  flarumExtend(UserPage.prototype, 'navItems', function (items: any) {
    const profileUser = this?.user ?? this?.attrs?.user;
    if (!profileUser) return;

    items.add(
      'blindboxes',
      <LinkButton href={app.route('user.blindboxes', { username: profileUser.slug() })} icon="fas fa-box-open">
        {app.translator.trans('donk-aigc-collectibles.forum.user.blindboxes_link')}
      </LinkButton>,
      49
    );

    items.add(
      'collectibles',
      <LinkButton href={app.route('user.collectibles', { username: profileUser.slug() })} icon="fas fa-gem">
        {app.translator.trans('donk-aigc-collectibles.forum.user.collectibles_link')}
      </LinkButton>,
      50
    );
  });
}
