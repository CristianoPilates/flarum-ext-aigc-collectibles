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

    const showcaseId = user.attribute<number>('showcaseCollectibleId');
    const showcaseName = user.attribute<string>('showcaseCollectibleName');
    const showcaseCid = user.attribute<string>('showcaseCollectibleCid');
    const showcaseRarity = user.attribute<string>('showcaseCollectibleRarity');

    if (!showcaseId || !showcaseCid) return null;

    const imageUrl = gatewayUrl(showcaseCid);

    return (
      <span
        className={'PostCollectibleBadge PostCollectibleBadge--' + (showcaseRarity || 'common')}
        title={showcaseName || ''}
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
