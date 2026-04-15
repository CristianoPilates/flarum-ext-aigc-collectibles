import Model from 'flarum/common/Model';
import User from 'flarum/common/models/User';

export default class BarterProposalItem extends Model {
  assetType() {
    return Model.attribute<string>('assetType').call(this);
  }

  assetId() {
    return Model.attribute<number>('assetId').call(this);
  }

  ownerUserId() {
    return Model.attribute<number>('ownerUserId').call(this);
  }

  position() {
    return Model.attribute<number>('position').call(this);
  }

  snapshot() {
    return Model.attribute<Record<string, any> | null>('snapshot').call(this);
  }

  createdAt() {
    return Model.attribute('createdAt', Model.transformDate).call(this);
  }

  updatedAt() {
    return Model.attribute('updatedAt', Model.transformDate).call(this);
  }

  ownerUser() {
    return Model.hasOne<User>('ownerUser').call(this);
  }
}
