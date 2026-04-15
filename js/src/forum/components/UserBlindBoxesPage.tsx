import app from 'flarum/forum/app';
import UserPage from 'flarum/forum/components/UserPage';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import BlindBoxInventory from './BlindBoxInventory';

export default class UserBlindBoxesPage extends UserPage {
  oninit(vnode: any) {
    super.oninit(vnode);
    this.loadUser(m.route.param('username'));
  }

  content() {
    const user = this.user;

    if (!user) {
      return <LoadingIndicator />;
    }

    const currentUser = app.session?.user;
    const isOwnProfile = Boolean(currentUser && currentUser.id() === user.id());

    if (!isOwnProfile) {
      return (
        <div className="BlindBoxInventory-empty">
          <p>{app.translator.trans('donk-aigc-collectibles.forum.blind_box.own_only')}</p>
        </div>
      );
    }

    return (
      <div className="UserBlindBoxesPage">
        <BlindBoxInventory user={user} />
      </div>
    );
  }
}
