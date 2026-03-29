import Model from 'flarum/common/Model';
import User from 'flarum/common/models/User';
import Collectible from './Collectible';

export default class Trade extends Model {
  static readonly STATUS_PENDING = 'pending';
  static readonly STATUS_ACCEPTED = 'accepted';
  static readonly STATUS_SETTLING = 'settling';
  static readonly STATUS_COMPLETED = 'completed';
  static readonly STATUS_REJECTED = 'rejected';
  static readonly STATUS_CANCELLED = 'cancelled';
  static readonly STATUS_FAILED = 'failed';

  offeredBoxes() {
    return Model.attribute<number>('offeredBoxes').call(this);
  }
  status() {
    return Model.attribute<string>('status').call(this);
  }
  note() {
    return Model.attribute<string | null>('note').call(this);
  }
  createdAt() {
    return Model.attribute('createdAt', Model.transformDate).call(this);
  }
  completedAt() {
    return Model.attribute('completedAt', Model.transformDate).call(this);
  }

  fromUser() {
    return Model.hasOne<User>('fromUser').call(this);
  }
  toUser() {
    return Model.hasOne<User>('toUser').call(this);
  }
  collectible() {
    return Model.hasOne<Collectible>('collectible').call(this);
  }
}
