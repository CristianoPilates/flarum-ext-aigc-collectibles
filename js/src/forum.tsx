import Extend from "flarum/common/extenders";
import app from "flarum/forum/app";
import User from "flarum/common/models/User";

import Collectible from "./forum/models/Collectible";
import BlindBox from "./forum/models/BlindBox";
import CheckinRecord from "./forum/models/CheckinRecord";
import BarterProposal from "./forum/models/BarterProposal";
import BarterProposalItem from "./forum/models/BarterProposalItem";

import UserCollectiblesPage from "./forum/components/UserCollectiblesPage";
import UserBlindBoxesPage from "./forum/components/UserBlindBoxesPage";

import { connect as wsConnect } from "./forum/utils/notifications";
import { installForumChrome } from "./forum/installers/forumChrome";
import { installBarterMessaging } from "./forum/installers/barterMessaging";

export const extend = [
  new Extend.Store()
    .add("blindboxes", BlindBox)
    .add("barter-proposal-items", BarterProposalItem)
    .add("barter-proposals", BarterProposal)
    .add("collectibles", Collectible)
    .add("checkin-records", CheckinRecord),

  new Extend.Routes().add(
    "user.blindboxes",
    "/u/:username/blindboxes",
    UserBlindBoxesPage
  ),

  new Extend.Routes().add(
    "user.collectibles",
    "/u/:username/collectibles",
    UserCollectiblesPage
  ),

  new Extend.Model(User)
    .attribute<number>("blindBoxCount")
    .attribute<boolean>("canCheckin")
    .attribute<boolean>("hasCheckedInToday")
    .attribute<string>("lastCheckinAt")
    .attribute<number>("showcaseCollectibleId")
    .attribute<string>("showcaseCollectibleName")
    .attribute<string>("showcaseCollectibleCid")
    .attribute<string>("showcaseCollectibleRarity")
    .attribute<number>("showcaseCollectibleTokenId")
    .attribute<string>("web3Address")
    .attribute<string>("web3AccountId"),
];

app.initializers.add("donk-aigc-collectibles", () => {
  installForumChrome();
  installBarterMessaging();

  // Connect WebSocket for real-time notifications
  if (app.session?.user) {
    wsConnect();
  }
});
