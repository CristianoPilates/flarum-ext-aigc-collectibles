import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';

export default class CheckinButton extends Component {
  loading: boolean = false;
  justCheckedIn: boolean = false;

  oninit(vnode: any) {
    super.oninit(vnode);
    this.loading = false;
    this.justCheckedIn = false;
  }

  view() {
    const user = app.session.user;
    if (!user) return null;

    const blindBoxCount = user.attribute<number>('blindBoxCount') || 0;
    const hasCheckedIn = this.hasCheckedInToday();

    return (
      <div className="CheckinButton">
        <Button
          className={'Button Button--primary CheckinButton-button' + (hasCheckedIn ? ' CheckinButton-button--done' : '')}
          onclick={() => this.checkin()}
          loading={this.loading}
          disabled={hasCheckedIn || this.loading}
          icon={hasCheckedIn ? 'fas fa-check' : 'fas fa-calendar-check'}
        >
          {hasCheckedIn
            ? app.translator.trans('donk-aigc-collectibles.forum.checkin.checked_in')
            : app.translator.trans('donk-aigc-collectibles.forum.checkin.check_in')}
        </Button>
        <span className="CheckinButton-balance" title={app.translator.trans('donk-aigc-collectibles.forum.blind_box.balance_title')}>
          <i className="fas fa-box" /> {blindBoxCount}
        </span>
      </div>
    );
  }

  hasCheckedInToday(): boolean {
    if (this.justCheckedIn) return true;

    const user = app.session.user;
    if (!user) return false;

    const lastCheckin = user.attribute<string>('lastCheckinAt');
    if (!lastCheckin) return false;

    const lastDate = new Date(lastCheckin);
    const today = new Date();

    return (
      lastDate.getFullYear() === today.getFullYear() &&
      lastDate.getMonth() === today.getMonth() &&
      lastDate.getDate() === today.getDate()
    );
  }

  checkin() {
    if (this.loading) return;

    this.loading = true;

    app
      .request({
        method: 'POST',
        url: app.forum.attribute('apiUrl') + '/checkin',
      })
      .then((response: any) => {
        this.loading = false;
        this.justCheckedIn = true;

        // Update user attributes in store
        const user = app.session.user;
        if (user && response?.data?.attributes) {
          user.pushAttributes({
            blindBoxCount: response.data.attributes.blindBoxCount,
            lastCheckinAt: response.data.attributes.lastCheckinAt,
          });
        }

        m.redraw();
      })
      .catch((error: any) => {
        this.loading = false;
        m.redraw();

        if (error.status === 409) {
          this.justCheckedIn = true;
          m.redraw();
        } else {
          throw error;
        }
      });
  }
}
