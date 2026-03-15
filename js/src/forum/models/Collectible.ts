import Model from 'flarum/common/Model';

export default class Collectible extends Model {
  name = Model.attribute<string>('name');
  ipfsCid = Model.attribute<string | null>('ipfsCid');
  metadataCid = Model.attribute<string | null>('metadataCid');
  rarity = Model.attribute<string>('rarity');
  status = Model.attribute<string>('status');
  tokenId = Model.attribute<number | null>('tokenId');
  timesTraded = Model.attribute<number>('timesTraded');
  aigcPrompt = Model.attribute<string | null>('aigcPrompt');
  createdAt = Model.attribute('createdAt', Model.transformDate);
  updatedAt = Model.attribute('updatedAt', Model.transformDate);

  user = Model.hasOne<any>('user');
  originalUser = Model.hasOne<any>('originalUser');
}
