import Model from 'flarum/common/Model';
import User from 'flarum/common/models/User';

export default class CheckinRecord extends Model {
  rewardAmount() {
    return Model.attribute<number>('rewardAmount').call(this);
  }
  checkedInAt() {
    return Model.attribute('checkedInAt', Model.transformDate).call(this);
  }

  user() {
    return Model.hasOne<User>('user').call(this);
  }
}
