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
    const user = app.session?.user;
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

    const user = app.session?.user;
    if (!user) return false;

    return Boolean(user.attribute<boolean>('hasCheckedInToday'));
  }

  checkin() {
    if (this.loading) return;

    this.loading = true;

    app
      .request({
        method: 'POST',
        url: app.forum.attribute('apiUrl') + '/checkin-records/checkin',
      })
      .then((response: any) => {
        this.loading = false;
        this.justCheckedIn = true;

        // Update user attributes in store
        const user = app.session?.user;
        if (user && response?.data?.attributes) {
          const attributes = response.data.attributes;
          const currentCount = user.attribute<number>('blindBoxCount') || 0;
          const rewardAmount = attributes.rewardAmount || 0;

          user.pushAttributes({
            canCheckin: false,
            hasCheckedInToday: true,
            blindBoxCount: attributes.blindBoxCount ?? currentCount + rewardAmount,
            lastCheckinAt: attributes.lastCheckinAt ?? attributes.checkedInAt ?? new Date().toISOString(),
          });
        }

        m.redraw();
      })
      .catch((error: any) => {
        this.loading = false;
        m.redraw();

        if (error.status === 409) {
          this.justCheckedIn = true;
          const user = app.session?.user;
          if (user) {
            user.pushAttributes({
              canCheckin: false,
              hasCheckedInToday: true,
            });
          }
          m.redraw();
        } else {
          throw error;
        }
      });
  }
}
