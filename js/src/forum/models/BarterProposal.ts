import Model from 'flarum/common/Model';
import User from 'flarum/common/models/User';
import BarterProposalItem from './BarterProposalItem';

export default class BarterProposal extends Model {
  threadType() {
    return Model.attribute<string>('threadType').call(this);
  }

  threadId() {
    return Model.attribute<number>('threadId').call(this);
  }

  revisionNumber() {
    return Model.attribute<number>('revisionNumber').call(this);
  }

  status() {
    return Model.attribute<string>('status').call(this);
  }

  message() {
    return Model.attribute<string | null>('message').call(this);
  }

  canAccept() {
    return Model.attribute<boolean>('canAccept').call(this);
  }

  canReject() {
    return Model.attribute<boolean>('canReject').call(this);
  }

  canCancel() {
    return Model.attribute<boolean>('canCancel').call(this);
  }

  completedAt() {
    return Model.attribute('completedAt', Model.transformDate).call(this);
  }

  createdAt() {
    return Model.attribute('createdAt', Model.transformDate).call(this);
  }

  updatedAt() {
    return Model.attribute('updatedAt', Model.transformDate).call(this);
  }

  proposer() {
    return Model.hasOne<User>('proposer').call(this);
  }

  counterparty() {
    return Model.hasOne<User>('counterparty').call(this);
  }

  acceptedBy() {
    return Model.hasOne<User>('acceptedBy').call(this);
  }

  replacesProposal() {
    return Model.hasOne<BarterProposal>('replacesProposal').call(this);
  }

  items() {
    return Model.hasMany<BarterProposalItem>('items').call(this) || [];
  }
}
