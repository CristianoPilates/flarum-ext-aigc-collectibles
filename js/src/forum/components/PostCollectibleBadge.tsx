import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import { gatewayUrl } from '../utils/ipfs';

interface PostCollectibleBadgeAttrs {
  user: any;
}

export default class PostCollectibleBadge extends Component<PostCollectibleBadgeAttrs> {
  view() {
    const user = this.attrs.user;
    if (!user) return null;

    const showcaseId = user.attribute('showcaseCollectibleId') as number | null;
    const showcaseName = user.attribute('showcaseCollectibleName') as string | null;
    const showcaseCid = user.attribute('showcaseCollectibleCid') as string | null;
    const showcaseRarity = user.attribute('showcaseCollectibleRarity') as string | null;
    const showcaseTokenId = user.attribute('showcaseCollectibleTokenId') as number | null;

    if (!showcaseId || !showcaseCid) return null;

    const imageUrl = gatewayUrl(showcaseCid);

    return (
      <span
        className={'PostCollectibleBadge PostCollectibleBadge--' + (showcaseRarity || 'common')}
        title={(showcaseName || '') + (showcaseTokenId ? ' · NFT #' + showcaseTokenId : '')}
      >
        {imageUrl ? (
          <img className="PostCollectibleBadge-image" src={imageUrl} alt={showcaseName || ''} loading="lazy" />
        ) : (
          <i className="fas fa-gem PostCollectibleBadge-icon" />
        )}
      </span>
    );
  }
}
