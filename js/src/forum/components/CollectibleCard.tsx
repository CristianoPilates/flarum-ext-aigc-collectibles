import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import { collectibleRarityLabel, displayCollectibleName } from '../utils/collectibles';
import { gatewayUrl } from '../utils/ipfs';
import { displayUserName } from '../utils/users';

interface CollectibleCardAttrs {
  collectible: any;
  onclick?: (collectible: any) => void;
  showOwner?: boolean;
  compact?: boolean;
}

export default class CollectibleCard extends Component<CollectibleCardAttrs> {
  imageLoaded: boolean = false;
  imageError: boolean = false;

  oninit(vnode: any) {
    super.oninit(vnode);
    this.imageLoaded = false;
    this.imageError = false;
  }

  view() {
    const { collectible, onclick, showOwner = false, compact = false } = this.attrs;

    if (!collectible) return null;

    const rarity = collectible.rarity();
    const status = collectible.status();
    const ipfsCid = collectible.ipfsCid();
    const imageUrl = ipfsCid ? gatewayUrl(ipfsCid) : null;
    const name = displayCollectibleName(collectible.name?.(), collectible.id?.());
    const owner = showOwner ? collectible.user() : null;

    const classes = [
      'CollectibleCard',
      'CollectibleCard--' + rarity,
      compact ? 'CollectibleCard--compact' : '',
      status !== 'completed' ? 'CollectibleCard--' + status : '',
      onclick ? 'CollectibleCard--clickable' : '',
    ]
      .filter(Boolean)
      .join(' ');

    return (
      <div className={classes} onclick={onclick ? () => onclick(collectible) : undefined}>
        <div className="CollectibleCard-image">
          {status === 'generating' ? (
            <div className="CollectibleCard-placeholder CollectibleCard-placeholder--generating">
              <i className="fas fa-spinner fa-spin" />
            </div>
          ) : imageUrl && !this.imageError ? (
            <img
              src={imageUrl}
              alt={name}
              loading="lazy"
              onload={() => {
                this.imageLoaded = true;
                m.redraw();
              }}
              onerror={() => {
                this.imageError = true;
                m.redraw();
              }}
              className={this.imageLoaded ? 'CollectibleCard-img--loaded' : ''}
            />
          ) : (
            <div className="CollectibleCard-placeholder">
              <i className="fas fa-image" />
              {this.imageError && <small>{app.translator.trans('donk-aigc-collectibles.forum.collectible.image_unavailable')}</small>}
            </div>
          )}
        </div>

        <div className="CollectibleCard-info">
          <span className="CollectibleCard-name">{name}</span>
          <span className={'CollectibleRarity CollectibleRarity--' + rarity}>{collectibleRarityLabel(rarity)}</span>
          {showOwner && owner && <span className="CollectibleCard-owner">{displayUserName(owner)}</span>}
          {collectible.tokenId() && (
            <span className="CollectibleCard-nft" title="NFT">
              <i className="fas fa-link" /> #{collectible.tokenId()}
            </span>
          )}
        </div>
      </div>
    );
  }
}
