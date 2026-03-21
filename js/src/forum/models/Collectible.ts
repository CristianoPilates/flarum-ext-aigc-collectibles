import Model from 'flarum/common/Model';
import User from 'flarum/common/models/User';

export default class Collectible extends Model {
  name() {
    return Model.attribute<string>('name').call(this);
  }
  ipfsCid() {
    return Model.attribute<string | null>('ipfsCid').call(this);
  }
  metadataCid() {
    return Model.attribute<string | null>('metadataCid').call(this);
  }
  rarity() {
    return Model.attribute<string>('rarity').call(this);
  }
  status() {
    return Model.attribute<string>('status').call(this);
  }
  tokenId() {
    return Model.attribute<number | null>('tokenId').call(this);
  }
  timesTraded() {
    return Model.attribute<number>('timesTraded').call(this);
  }
  aigcPrompt() {
    return Model.attribute<string | null>('aigcPrompt').call(this);
  }
  createdAt() {
    return Model.attribute('createdAt', Model.transformDate).call(this);
  }
  updatedAt() {
    return Model.attribute('updatedAt', Model.transformDate).call(this);
  }

  user() {
    return Model.hasOne<User>('user').call(this);
  }
  originalUser() {
    return Model.hasOne<User>('originalUser').call(this);
  }
}
