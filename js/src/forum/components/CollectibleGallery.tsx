import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import CollectibleCard from './CollectibleCard';

interface CollectibleGalleryAttrs {
  user: any;
  refreshToken?: number;
  onSelect?: (collectible: any) => void;
}

const RARITIES = ['all', 'common', 'rare', 'epic', 'legendary'];

export default class CollectibleGallery extends Component<CollectibleGalleryAttrs> {
  loading: boolean = true;
  collectibles: any[] = [];
  rarityFilter: string = 'all';
  hasMore: boolean = false;
  offset: number = 0;
  readonly limit: number = 20;
  lastLoadedUserId: string | null = null;
  lastRefreshToken: number = 0;

  oninit(vnode: any) {
    super.oninit(vnode);
    this.resetState();
    this.lastLoadedUserId = vnode.attrs.user?.id?.() || null;
    this.lastRefreshToken = vnode.attrs.refreshToken || 0;
    this.loadCollectibles();
  }

  onbeforeupdate(vnode: any) {
    const userId = vnode.attrs.user?.id?.() || null;
    const refreshToken = vnode.attrs.refreshToken || 0;

    if (userId !== this.lastLoadedUserId || refreshToken !== this.lastRefreshToken) {
      this.lastLoadedUserId = userId;
      this.lastRefreshToken = refreshToken;
      this.resetState();
      this.loadCollectibles();
    }

    return true;
  }

  view() {
    return (
      <div className="CollectibleGallery">
        <div className="CollectibleGallery-header">
          <h3 className="CollectibleGallery-title">
            {app.translator.trans('donk-aigc-collectibles.forum.gallery.title')}
          </h3>
          <div className="CollectibleGallery-filters">
            {RARITIES.map((rarity) => (
              <Button
                className={'Button Button--text CollectibleGallery-filter' + (this.rarityFilter === rarity ? ' active' : '')}
                onclick={() => this.setFilter(rarity)}
              >
                {rarity === 'all'
                  ? app.translator.trans('donk-aigc-collectibles.forum.gallery.filter_all')
                  : app.translator.trans('donk-aigc-collectibles.forum.gallery.filter_' + rarity)}
              </Button>
            ))}
          </div>
        </div>

        {this.loading && this.collectibles.length === 0 ? (
          <div className="CollectibleGallery-loading">
            <LoadingIndicator />
          </div>
        ) : this.filteredCollectibles().length === 0 ? (
          <div className="CollectibleGallery-empty">
            <p>{app.translator.trans('donk-aigc-collectibles.forum.gallery.empty')}</p>
          </div>
        ) : (
          <div className="CollectibleGallery-grid">
            {this.filteredCollectibles().map((collectible: any) => (
              <CollectibleCard collectible={collectible} onclick={this.attrs.onSelect} />
            ))}
          </div>
        )}

        {this.hasMore && (
          <div className="CollectibleGallery-loadMore">
            <Button className="Button" onclick={() => this.loadMore()} loading={this.loading}>
              {app.translator.trans('donk-aigc-collectibles.forum.gallery.load_more')}
            </Button>
          </div>
        )}
      </div>
    );
  }

  filteredCollectibles(): any[] {
    if (this.rarityFilter === 'all') return this.collectibles;
    return this.collectibles.filter((c: any) => c.rarity() === this.rarityFilter);
  }

  setFilter(rarity: string) {
    this.rarityFilter = rarity;
  }

  resetState() {
    this.loading = true;
    this.collectibles = [];
    this.rarityFilter = 'all';
    this.hasMore = false;
    this.offset = 0;
  }

  loadCollectibles() {
    this.loading = true;

    const userId = this.attrs.user?.id();
    if (!userId) {
      this.loading = false;
      return;
    }

    const params: Record<string, any> = {
      'filter[user]': userId,
      'page[offset]': this.offset,
      'page[limit]': this.limit,
      include: 'owner',
      sort: '-createdAt',
    };

    app
      .request({
        method: 'GET',
        url: app.forum.attribute('apiUrl') + '/collectibles',
        params,
      })
      .then((response: any) => {
        const newItems = app.store.pushPayload(response);
        if (Array.isArray(newItems)) {
          this.collectibles = this.offset === 0 ? newItems : [...this.collectibles, ...newItems];
          this.hasMore = newItems.length >= this.limit;
        }
        this.loading = false;
        m.redraw();
      })
      .catch(() => {
        this.loading = false;
        m.redraw();
      });
  }

  loadMore() {
    this.offset += this.limit;
    this.loadCollectibles();
  }
}
