import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';

export default class AigcCollectiblesSettings extends ExtensionPage {
  content() {
    return (
      <div className="AigcCollectiblesSettings">
        <div className="container">
          {this.buildSettingComponent({
            setting: 'donk-aigc-collectibles.checkin-reward',
            type: 'number',
            label: app.translator.trans('donk-aigc-collectibles.admin.settings.checkin_reward_label'),
            help: app.translator.trans('donk-aigc-collectibles.admin.settings.checkin_reward_help'),
            min: 1,
            placeholder: '1',
          })}

          <h3>{app.translator.trans('donk-aigc-collectibles.admin.settings.section_aigc')}</h3>

          {this.buildSettingComponent({
            setting: 'donk-aigc-collectibles.aigc-enabled',
            type: 'boolean',
            label: app.translator.trans('donk-aigc-collectibles.admin.settings.aigc_enabled_label'),
          })}

          {this.buildSettingComponent({
            setting: 'donk-aigc-collectibles.aigc-api-url',
            type: 'text',
            label: app.translator.trans('donk-aigc-collectibles.admin.settings.aigc_endpoint_label'),
            placeholder: 'https://api.openai.com/v1/images/generations',
          })}

          {this.buildSettingComponent({
            setting: 'donk-aigc-collectibles.aigc-api-key',
            type: 'password',
            label: app.translator.trans('donk-aigc-collectibles.admin.settings.aigc_api_key_label'),
          })}

          {this.buildSettingComponent({
            setting: 'donk-aigc-collectibles.aigc-model',
            type: 'text',
            label: app.translator.trans('donk-aigc-collectibles.admin.settings.aigc_model_label'),
            placeholder: 'dall-e-3',
          })}

          <h3>{app.translator.trans('donk-aigc-collectibles.admin.settings.section_ipfs')}</h3>

          {this.buildSettingComponent({
            setting: 'donk-aigc-collectibles.ipfs-api-url',
            type: 'text',
            label: app.translator.trans('donk-aigc-collectibles.admin.settings.ipfs_endpoint_label'),
            placeholder: 'https://api.pinata.cloud',
          })}

          {this.buildSettingComponent({
            setting: 'donk-aigc-collectibles.ipfs-gateway-url',
            type: 'text',
            label: app.translator.trans('donk-aigc-collectibles.admin.settings.ipfs_gateway_label'),
            placeholder: 'https://ipfs.io/ipfs/',
          })}

          <h3>{app.translator.trans('donk-aigc-collectibles.admin.settings.section_blockchain')}</h3>

          {this.buildSettingComponent({
            setting: 'donk-aigc-collectibles.blockchain-enabled',
            type: 'boolean',
            label: app.translator.trans('donk-aigc-collectibles.admin.settings.blockchain_enabled_label'),
          })}

          {this.buildSettingComponent({
            setting: 'donk-aigc-collectibles.blockchain-rpc-url',
            type: 'text',
            label: app.translator.trans('donk-aigc-collectibles.admin.settings.blockchain_rpc_url_label'),
            placeholder: 'http://127.0.0.1:8545',
          })}

          {this.buildSettingComponent({
            setting: 'donk-aigc-collectibles.nft-contract-address',
            type: 'text',
            label: app.translator.trans('donk-aigc-collectibles.admin.settings.blockchain_contract_address_label'),
            placeholder: '0x...',
          })}

          <h3>{app.translator.trans('donk-aigc-collectibles.admin.settings.section_rarity')}</h3>

          {this.buildSettingComponent({
            setting: 'donk-aigc-collectibles.rarity-common',
            type: 'number',
            label: app.translator.trans('donk-aigc-collectibles.admin.settings.rarity_common_label'),
            min: 0,
            max: 100,
            placeholder: '60',
          })}

          {this.buildSettingComponent({
            setting: 'donk-aigc-collectibles.rarity-rare',
            type: 'number',
            label: app.translator.trans('donk-aigc-collectibles.admin.settings.rarity_rare_label'),
            min: 0,
            max: 100,
            placeholder: '25',
          })}

          {this.buildSettingComponent({
            setting: 'donk-aigc-collectibles.rarity-epic',
            type: 'number',
            label: app.translator.trans('donk-aigc-collectibles.admin.settings.rarity_epic_label'),
            min: 0,
            max: 100,
            placeholder: '12',
          })}

          {this.buildSettingComponent({
            setting: 'donk-aigc-collectibles.rarity-legendary',
            type: 'number',
            label: app.translator.trans('donk-aigc-collectibles.admin.settings.rarity_legendary_label'),
            min: 0,
            max: 100,
            placeholder: '3',
          })}

          {this.submitButton()}
        </div>
      </div>
    );
  }
}
