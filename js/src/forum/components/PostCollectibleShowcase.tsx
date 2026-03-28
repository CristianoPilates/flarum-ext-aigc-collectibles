import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import CollectibleDetailModal from './CollectibleDetailModal';
import { gatewayUrl } from '../utils/ipfs';

interface PostCollectibleShowcaseAttrs {
  user: any;
}

export default class PostCollectibleShowcase extends Component<PostCollectibleShowcaseAttrs> {
  view() {
    const user = this.attrs.user;
    if (!user) return null;

    const showcaseId = user.attribute<number>('showcaseCollectibleId');
    const showcaseName = user.attribute<string>('showcaseCollectibleName');
    const showcaseCid = user.attribute<string>('showcaseCollectibleCid');
    const showcaseRarity = user.attribute<string>('showcaseCollectibleRarity');
    const showcaseTokenId = user.attribute<number>('showcaseCollectibleTokenId');

    if (!showcaseId || !showcaseCid) return null;

    const imageUrl = gatewayUrl(showcaseCid);
    const collectible = app.store.getById('collectibles', String(showcaseId));

    return (
      <button
        type="button"
        className={'PostCollectibleShowcase PostCollectibleShowcase--' + (showcaseRarity || 'common')}
        onclick={() => this.openCollectibleDetail(collectible, showcaseId)}
      >
        <div className="PostCollectibleShowcase-frame">
          {imageUrl ? (
            <img className="PostCollectibleShowcase-image" src={imageUrl} alt={showcaseName || ''} loading="lazy" />
          ) : (
            <div className="PostCollectibleShowcase-placeholder">
              <i className="fas fa-gem" />
            </div>
          )}
        </div>

        <div className="PostCollectibleShowcase-copy">
          <div className="PostCollectibleShowcase-kicker">
            {app.translator.trans('donk-aigc-collectibles.forum.post_showcase.kicker')}
          </div>
          <div className="PostCollectibleShowcase-name">{showcaseName || 'Collectible #' + showcaseId}</div>
          <div className="PostCollectibleShowcase-meta">
            <span className={'CollectibleRarity CollectibleRarity--' + (showcaseRarity || 'common')}>
              {this.rarityLabel(showcaseRarity)}
            </span>
            {showcaseTokenId ? (
              <span className="PostCollectibleShowcase-token">
                {app.translator.trans('donk-aigc-collectibles.forum.collectible.token_id_label', { id: showcaseTokenId })}
              </span>
            ) : null}
          </div>
        </div>
      </button>
    );
  }

  rarityLabel(rarity?: string | null) {
    const key = rarity || 'common';

    return app.translator.trans('donk-aigc-collectibles.forum.collectible.rarity_' + key);
  }

  openCollectibleDetail(collectible: any, showcaseId: number) {
    const user = this.attrs.user;
    const isOwnProfile = Boolean(app.session?.user && user && app.session.user.id() === user.id?.());

    if (collectible) {
      app.modal.show(CollectibleDetailModal, {
        collectible,
        isOwnProfile,
      });

      return;
    }

    const apiUrl = app.forum.attribute('apiUrl');
    if (!apiUrl) return;

    app
      .request({
        method: 'GET',
        url: apiUrl + '/collectibles/' + showcaseId,
      })
      .then((response: any) => {
        app.store.pushPayload(response);
        const loadedCollectible = app.store.getById('collectibles', String(showcaseId));

        if (!loadedCollectible) return;

        app.modal.show(CollectibleDetailModal, {
          collectible: loadedCollectible,
          isOwnProfile,
        });
      });
  }
}
