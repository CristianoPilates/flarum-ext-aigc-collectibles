import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import TradeRequestModal from './TradeRequestModal';

interface TradePanelAttrs {
  user: any;
}

const STATUS_ICONS: Record<string, string> = {
  pending: 'fas fa-clock',
  accepted: 'fas fa-check-circle',
  rejected: 'fas fa-times-circle',
  cancelled: 'fas fa-ban',
};

export default class TradePanel extends Component<TradePanelAttrs> {
  loading: boolean = true;
  trades: any[] = [];
  activeTab: string = 'incoming';

  oninit(vnode: any) {
    super.oninit(vnode);
    this.loading = true;
    this.trades = [];
    this.activeTab = 'incoming';
    this.loadTrades();
  }

  view() {
    const currentUser = app.session.user;
    if (!currentUser) return null;

    const isOwnProfile = this.attrs.user && this.attrs.user.id() === currentUser.id();

    if (!isOwnProfile) return null;

    return (
      <div className="TradePanel">
        <h3 className="TradePanel-title">
          {app.translator.trans('donk-aigc-collectibles.forum.trade.title')}
        </h3>

        <div className="TradePanel-tabs">
          <Button
            className={'Button Button--text TradePanel-tab' + (this.activeTab === 'incoming' ? ' active' : '')}
            onclick={() => this.setTab('incoming')}
          >
            {app.translator.trans('donk-aigc-collectibles.forum.trade.incoming')}
          </Button>
          <Button
            className={'Button Button--text TradePanel-tab' + (this.activeTab === 'outgoing' ? ' active' : '')}
            onclick={() => this.setTab('outgoing')}
          >
            {app.translator.trans('donk-aigc-collectibles.forum.trade.outgoing')}
          </Button>
          <Button
            className={'Button Button--text TradePanel-tab' + (this.activeTab === 'history' ? ' active' : '')}
            onclick={() => this.setTab('history')}
          >
            {app.translator.trans('donk-aigc-collectibles.forum.trade.history')}
          </Button>
        </div>

        <div className="TradePanel-content">
          {this.loading ? (
            <LoadingIndicator />
          ) : this.filteredTrades().length === 0 ? (
            <p className="TradePanel-empty">
              {app.translator.trans('donk-aigc-collectibles.forum.trade.empty')}
            </p>
          ) : (
            <ul className="TradePanel-list">
              {this.filteredTrades().map((trade: any) => this.viewTradeItem(trade))}
            </ul>
          )}
        </div>
      </div>
    );
  }

  viewTradeItem(trade: any) {
    const currentUser = app.session.user;
    if (!currentUser) return null;

    const status = trade.status();
    const isIncoming = trade.toUser()?.id() === currentUser.id();
    const otherUser = isIncoming ? trade.fromUser() : trade.toUser();
    const collectible = trade.collectible();
    const isPending = status === 'pending';

    return (
      <li className={'TradePanel-item TradePanel-item--' + status}>
        <div className="TradePanel-itemInfo">
          <span className="TradePanel-itemUser">
            {otherUser ? otherUser.displayName() : app.translator.trans('donk-aigc-collectibles.forum.trade.unknown_user')}
          </span>
          <span className="TradePanel-itemArrow">
            {isIncoming ? (
              <span>
                <i className="fas fa-arrow-right" />{' '}
                {app.translator.trans('donk-aigc-collectibles.forum.trade.offers_you')}
              </span>
            ) : (
              <span>
                <i className="fas fa-arrow-left" />{' '}
                {app.translator.trans('donk-aigc-collectibles.forum.trade.you_offered')}
              </span>
            )}
          </span>
          <span className="TradePanel-itemBoxes">
            <i className="fas fa-box" /> {trade.offeredBoxes()}
          </span>
          {collectible && <span className="TradePanel-itemCollectible">{collectible.name()}</span>}
        </div>

        <div className="TradePanel-itemStatus">
          <span className={'TradePanel-statusBadge TradePanel-statusBadge--' + status}>
            <i className={STATUS_ICONS[status] || 'fas fa-question'} /> {status}
          </span>
        </div>

        {isPending && (
          <div className="TradePanel-itemActions">
            {isIncoming ? (
              [
                <Button
                  className="Button Button--primary Button--small"
                  onclick={() => this.acceptTrade(trade)}
                  icon="fas fa-check"
                >
                  {app.translator.trans('donk-aigc-collectibles.forum.trade.accept')}
                </Button>,
                <Button
                  className="Button Button--danger Button--small"
                  onclick={() => this.rejectTrade(trade)}
                  icon="fas fa-times"
                >
                  {app.translator.trans('donk-aigc-collectibles.forum.trade.reject')}
                </Button>,
              ]
            ) : (
              <Button
                className="Button Button--danger Button--small"
                onclick={() => this.cancelTrade(trade)}
                icon="fas fa-ban"
              >
                {app.translator.trans('donk-aigc-collectibles.forum.trade.cancel')}
              </Button>
            )}
          </div>
        )}
      </li>
    );
  }

  filteredTrades(): any[] {
    const currentUser = app.session.user;
    if (!currentUser) return [];

    return this.trades.filter((trade: any) => {
      const status = trade.status();
      const isIncoming = trade.toUser()?.id() === currentUser.id();
      const isOutgoing = trade.fromUser()?.id() === currentUser.id();

      switch (this.activeTab) {
        case 'incoming':
          return isIncoming && status === 'pending';
        case 'outgoing':
          return isOutgoing && status === 'pending';
        case 'history':
          return status !== 'pending';
        default:
          return true;
      }
    });
  }

  setTab(tab: string) {
    this.activeTab = tab;
  }

  loadTrades() {
    this.loading = true;

    app
      .request({
        method: 'GET',
        url: app.forum.attribute('apiUrl') + '/trades',
        params: {
          include: 'fromUser,toUser,collectible',
        },
      })
      .then((response: any) => {
        this.trades = app.store.pushPayload(response);
        if (!Array.isArray(this.trades)) {
          this.trades = [this.trades];
        }
        this.loading = false;
        m.redraw();
      })
      .catch(() => {
        this.loading = false;
        m.redraw();
      });
  }

  acceptTrade(trade: any) {
    app
      .request({
        method: 'POST',
        url: app.forum.attribute('apiUrl') + '/trades/' + trade.id() + '/accept',
      })
      .then(() => {
        this.loadTrades();
      });
  }

  rejectTrade(trade: any) {
    app
      .request({
        method: 'POST',
        url: app.forum.attribute('apiUrl') + '/trades/' + trade.id() + '/reject',
      })
      .then(() => {
        this.loadTrades();
      });
  }

  cancelTrade(trade: any) {
    app
      .request({
        method: 'DELETE',
        url: app.forum.attribute('apiUrl') + '/trades/' + trade.id(),
      })
      .then(() => {
        this.loadTrades();
      });
  }
}
