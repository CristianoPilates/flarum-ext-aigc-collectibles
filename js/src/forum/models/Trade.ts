import Model from 'flarum/common/Model';

export default class Trade extends Model {
  offeredBoxes = Model.attribute<number>('offeredBoxes');
  status = Model.attribute<string>('status');
  note = Model.attribute<string | null>('note');
  createdAt = Model.attribute('createdAt', Model.transformDate);
  completedAt = Model.attribute('completedAt', Model.transformDate);

  fromUser = Model.hasOne<any>('fromUser');
  toUser = Model.hasOne<any>('toUser');
  collectible = Model.hasOne<any>('collectible');
}
