import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import TradeModel from '../models/Trade';
import { displayUserName } from '../utils/users';

interface TradePanelAttrs {
  user: any;
  onChanged?: () => void;
}

const STATUS_ICONS: Record<string, string> = {
  [TradeModel.STATUS_PENDING]: 'fas fa-clock',
  [TradeModel.STATUS_ACCEPTED]: 'fas fa-handshake',
  [TradeModel.STATUS_SETTLING]: 'fas fa-spinner fa-spin',
  [TradeModel.STATUS_COMPLETED]: 'fas fa-check-circle',
  [TradeModel.STATUS_REJECTED]: 'fas fa-times-circle',
  [TradeModel.STATUS_CANCELLED]: 'fas fa-ban',
  [TradeModel.STATUS_FAILED]: 'fas fa-exclamation-triangle',
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
    const currentUser = app.session?.user;
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
    const currentUser = app.session?.user;
    if (!currentUser) return null;

    const status = trade.status();
    const isIncoming = trade.toUser()?.id() === currentUser.id();
    const otherUser = isIncoming ? trade.fromUser() : trade.toUser();
    const collectible = trade.collectible();
    const isPending = status === TradeModel.STATUS_PENDING;

    return (
      <li className={'TradePanel-item TradePanel-item--' + status}>
        <div className="TradePanel-itemInfo">
          <span className="TradePanel-itemUser">
            {otherUser ? displayUserName(otherUser) : app.translator.trans('donk-aigc-collectibles.forum.trade.unknown_user')}
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
    const currentUser = app.session?.user;
    if (!currentUser) return [];

    return this.trades.filter((trade: any) => {
      const status = trade.status();
      const isIncoming = trade.toUser()?.id() === currentUser.id();
      const isOutgoing = trade.fromUser()?.id() === currentUser.id();

      switch (this.activeTab) {
        case 'incoming':
          return isIncoming && status === TradeModel.STATUS_PENDING;
        case 'outgoing':
          return isOutgoing && status === TradeModel.STATUS_PENDING;
        case 'history':
          return status !== TradeModel.STATUS_PENDING;
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
        const payload = app.store.pushPayload(response);
        this.trades = Array.isArray(payload) ? payload : payload ? [payload] : [];
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
        this.attrs.onChanged?.();
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
        this.attrs.onChanged?.();
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
        this.attrs.onChanged?.();
      });
  }
}
