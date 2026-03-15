import Model from 'flarum/common/Model';

export default class CheckinRecord extends Model {
  rewardAmount = Model.attribute<number>('rewardAmount');
  checkedInAt = Model.attribute('checkedInAt', Model.transformDate);

  user = Model.hasOne<any>('user');
}
