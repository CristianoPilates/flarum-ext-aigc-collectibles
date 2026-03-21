import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import HeaderSecondary from 'flarum/forum/components/HeaderSecondary';
import UserPage from 'flarum/forum/components/UserPage';
import PostUser from 'flarum/forum/components/PostUser';
import LinkButton from 'flarum/common/components/LinkButton';

import Collectible from './forum/models/Collectible';
import Trade from './forum/models/Trade';
import CheckinRecord from './forum/models/CheckinRecord';

import CheckinButton from './forum/components/CheckinButton';
import PostCollectibleBadge from './forum/components/PostCollectibleBadge';
import BlindBoxOpener from './forum/components/BlindBoxOpener';
import CollectibleGallery from './forum/components/CollectibleGallery';
import TradePanel from './forum/components/TradePanel';
import WalletConnector from './forum/components/WalletConnector';
import UserCollectiblesPage from './forum/components/UserCollectiblesPage';

import { connect as wsConnect, subscribe, unsubscribe } from './forum/utils/notifications';

app.initializers.add('donk-aigc-collectibles', () => {
  // Register models with the store
  app.store.models.collectibles = Collectible;
  app.store.models.trades = Trade;
  app.store.models['checkin-records'] = CheckinRecord;

  // Add check-in button to header
  extend(HeaderSecondary.prototype, 'items', function (items: any) {
    if (app.session.user) {
      items.add(
        'donk-aigc-collectibles-checkin',
        <CheckinButton />,
        15
      );

      // Blind box opener button
      items.add(
        'donk-aigc-collectibles-blindbox',
        <button
          className="Button Button--link BlindBoxOpener-trigger"
          onclick={() => app.modal.show(BlindBoxOpener)}
          title={app.translator.trans('donk-aigc-collectibles.forum.blind_box.open_title')}
        >
          <i className="fas fa-box-open" />
        </button>,
        14
      );
    }
  });

  // Add collectible badge next to post author
  extend(PostUser.prototype, 'view', function (vnode: any) {
    if (!vnode || !this.attrs.post) return;

    const user = this.attrs.post.user();
    if (!user) return;

    const showcaseId = user.attribute('showcaseCollectibleId');
    if (!showcaseId) return;

    // Ensure vnode.children is an array we can append to
    if (!vnode.children) {
      vnode.children = [];
    }

    if (Array.isArray(vnode.children)) {
      vnode.children.push(<PostCollectibleBadge user={user} />);
    }
  });

  // Add collectibles tab to user profile
  extend(UserPage.prototype, 'navItems', function (items: any) {
    const user = this.user;
    if (!user) return;

    items.add(
      'collectibles',
      <LinkButton href={app.route('user.collectibles', { username: user.slug() })} icon="fas fa-gem">
        {app.translator.trans('donk-aigc-collectibles.forum.user.collectibles_link')}
      </LinkButton>,
      50
    );
  });

  // Register user profile route for collectibles
  app.routes['user.collectibles'] = {
    path: '/u/:username/collectibles',
    component: UserCollectiblesPage,
  };

  // Connect WebSocket for real-time notifications
  if (app.session.user) {
    wsConnect();

    // Listen for trade notifications
    subscribe('trade.created', (data: any) => {
      app.alerts.show(
        { type: 'info' },
        app.translator.trans('donk-aigc-collectibles.forum.trade.notification_received')
      );
    });

    subscribe('trade.accepted', (data: any) => {
      app.alerts.show(
        { type: 'success' },
        app.translator.trans('donk-aigc-collectibles.forum.trade.notification_accepted')
      );
    });

    subscribe('trade.rejected', (data: any) => {
      app.alerts.show(
        { type: 'info' },
        app.translator.trans('donk-aigc-collectibles.forum.trade.notification_rejected')
      );
    });
  }
});
