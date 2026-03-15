import app from 'flarum/forum/app';
import UserPage from 'flarum/forum/components/UserPage';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import CollectibleGallery from './CollectibleGallery';
import TradePanel from './TradePanel';
import WalletConnector from './WalletConnector';

export default class UserCollectiblesPage extends UserPage {
  oninit(vnode: any) {
    super.oninit(vnode);
    this.loadUser(m.route.param('username'));
  }

  content() {
    const user = this.user;

    if (!user) {
      return <LoadingIndicator />;
    }

    const isOwnProfile = app.session.user && app.session.user.id() === user.id();

    return (
      <div className="UserCollectiblesPage">
        <CollectibleGallery user={user} />

        {isOwnProfile && (
          <div className="UserCollectiblesPage-sidebar">
            <TradePanel user={user} />
            <WalletConnector user={user} />
          </div>
        )}
      </div>
    );
  }
}
