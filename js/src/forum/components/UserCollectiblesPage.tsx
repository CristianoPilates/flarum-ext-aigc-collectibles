import app from 'flarum/forum/app';
import UserPage from 'flarum/forum/components/UserPage';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import CollectibleGallery from './CollectibleGallery';
import TradePanel from './TradePanel';
import WalletConnector from './WalletConnector';
import CollectibleDetailModal from './CollectibleDetailModal';

export default class UserCollectiblesPage extends UserPage {
  refreshToken: number = 0;

  oninit(vnode: any) {
    super.oninit(vnode);
    this.refreshToken = 0;
    this.loadUser(m.route.param('username'));
  }

  content() {
    const user = this.user;

    if (!user) {
      return <LoadingIndicator />;
    }

    const isOwnProfile = app.session?.user && app.session?.user.id() === user.id();

    return (
      <div className="UserCollectiblesPage">
        <CollectibleGallery
          user={user}
          refreshToken={this.refreshToken}
          onSelect={(collectible: any) => this.openCollectible(collectible, isOwnProfile)}
        />

        {isOwnProfile && (
          <div className="UserCollectiblesPage-sidebar">
            <TradePanel user={user} onChanged={() => this.refreshData()} />
            <WalletConnector user={user} />
          </div>
        )}
      </div>
    );
  }

  refreshData() {
    this.refreshToken += 1;
    this.loadUser(m.route.param('username'));
    m.redraw();
  }

  openCollectible(collectible: any, isOwnProfile: boolean) {
    app.modal.show(CollectibleDetailModal, {
      collectible,
      isOwnProfile,
      onUpdated: () => this.refreshData(),
    });
  }
}
