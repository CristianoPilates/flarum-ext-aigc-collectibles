import Model from 'flarum/common/Model';

export default class BlindBox extends Model {
  type() {
    return Model.attribute<string>('type').call(this);
  }

  seed() {
    return Model.attribute<string>('seed').call(this);
  }

  status() {
    return Model.attribute<string>('status').call(this);
  }

  budget() {
    return Model.attribute<number | null>('budget').call(this);
  }

  drawRules() {
    return Model.attribute<Array<{ category: string; required: boolean }>>('drawRules').call(this) || [];
  }

  createdAt() {
    return Model.attribute('createdAt', Model.transformDate).call(this);
  }

  updatedAt() {
    return Model.attribute('updatedAt', Model.transformDate).call(this);
  }
}
