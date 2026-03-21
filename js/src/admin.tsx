import Extend from 'flarum/common/extenders';
import app from 'flarum/admin/app';

export const extend = [
  new Extend.Admin()
    // -- Check-in Settings --
    .setting(() => ({
      setting: 'donk-aigc-collectibles.checkin-reward',
      type: 'number',
      label: app.translator.trans('donk-aigc-collectibles.admin.settings.checkin_reward_amount_label'),
      help: app.translator.trans('donk-aigc-collectibles.admin.settings.checkin_reward_amount_help'),
      min: 1,
      placeholder: '1',
    }))

    // -- AIGC API Settings --
    .customSetting(() => <h3>{app.translator.trans('donk-aigc-collectibles.admin.settings.section_aigc')}</h3>)
    .setting(() => ({
      setting: 'donk-aigc-collectibles.aigc-enabled',
      type: 'boolean',
      label: app.translator.trans('donk-aigc-collectibles.admin.settings.aigc_enabled_label'),
    }))
    .setting(() => ({
      setting: 'donk-aigc-collectibles.aigc-api-url',
      type: 'text',
      label: app.translator.trans('donk-aigc-collectibles.admin.settings.aigc_api_endpoint_label'),
      help: app.translator.trans('donk-aigc-collectibles.admin.settings.aigc_api_endpoint_help'),
      placeholder: 'https://api.openai.com/v1/images/generations',
    }))
    .setting(() => ({
      setting: 'donk-aigc-collectibles.aigc-api-key',
      type: 'password',
      label: app.translator.trans('donk-aigc-collectibles.admin.settings.aigc_api_key_label'),
      help: app.translator.trans('donk-aigc-collectibles.admin.settings.aigc_api_key_help'),
    }))
    .setting(() => ({
      setting: 'donk-aigc-collectibles.aigc-model',
      type: 'text',
      label: app.translator.trans('donk-aigc-collectibles.admin.settings.aigc_model_label'),
      help: app.translator.trans('donk-aigc-collectibles.admin.settings.aigc_model_help'),
      placeholder: 'dall-e-3',
    }))
    .setting(() => ({
      setting: 'donk-aigc-collectibles.aigc-base-prompt',
      type: 'textarea',
      label: app.translator.trans('donk-aigc-collectibles.admin.settings.aigc_default_prompt_label'),
      help: app.translator.trans('donk-aigc-collectibles.admin.settings.aigc_default_prompt_help'),
      placeholder: 'A beautiful digital artwork of a {rarity} collectible, fantasy style, vibrant colors',
    }))

    // -- IPFS Settings --
    .customSetting(() => <h3>{app.translator.trans('donk-aigc-collectibles.admin.settings.section_ipfs')}</h3>)
    .setting(() => ({
      setting: 'donk-aigc-collectibles.ipfs-api-url',
      type: 'text',
      label: app.translator.trans('donk-aigc-collectibles.admin.settings.ipfs_api_endpoint_label'),
      help: app.translator.trans('donk-aigc-collectibles.admin.settings.ipfs_api_endpoint_help'),
      placeholder: 'https://api.pinata.cloud',
    }))
    .setting(() => ({
      setting: 'donk-aigc-collectibles.ipfs-api-key',
      type: 'password',
      label: app.translator.trans('donk-aigc-collectibles.admin.settings.ipfs_api_key_label'),
      help: app.translator.trans('donk-aigc-collectibles.admin.settings.ipfs_api_key_help'),
    }))
    .setting(() => ({
      setting: 'donk-aigc-collectibles.ipfs-gateway-url',
      type: 'text',
      label: app.translator.trans('donk-aigc-collectibles.admin.settings.ipfs_gateway_url_label'),
      help: app.translator.trans('donk-aigc-collectibles.admin.settings.ipfs_gateway_url_help'),
      placeholder: 'https://ipfs.io/ipfs/',
    }))

    // -- Blockchain Settings --
    .customSetting(() => <h3>{app.translator.trans('donk-aigc-collectibles.admin.settings.section_blockchain')}</h3>)
    .setting(() => ({
      setting: 'donk-aigc-collectibles.blockchain-enabled',
      type: 'boolean',
      label: app.translator.trans('donk-aigc-collectibles.admin.settings.blockchain_enabled_label'),
    }))
    .setting(() => ({
      setting: 'donk-aigc-collectibles.blockchain-rpc-url',
      type: 'text',
      label: app.translator.trans('donk-aigc-collectibles.admin.settings.blockchain_rpc_url_label'),
      help: app.translator.trans('donk-aigc-collectibles.admin.settings.blockchain_rpc_url_help'),
      placeholder: 'http://127.0.0.1:8545',
    }))
    .setting(() => ({
      setting: 'donk-aigc-collectibles.nft-contract-address',
      type: 'text',
      label: app.translator.trans('donk-aigc-collectibles.admin.settings.blockchain_contract_address_label'),
      help: app.translator.trans('donk-aigc-collectibles.admin.settings.blockchain_contract_address_help'),
      placeholder: '0x...',
    }))
    .setting(() => ({
      setting: 'donk-aigc-collectibles.minter-private-key',
      type: 'password',
      label: app.translator.trans('donk-aigc-collectibles.admin.settings.blockchain_private_key_label'),
      help: app.translator.trans('donk-aigc-collectibles.admin.settings.blockchain_private_key_help'),
    }))

    // -- Rarity Weights --
    .customSetting(() => <h3>{app.translator.trans('donk-aigc-collectibles.admin.settings.section_rarity')}</h3>)
    .setting(() => ({
      setting: 'donk-aigc-collectibles.rarity-common',
      type: 'number',
      label: app.translator.trans('donk-aigc-collectibles.admin.settings.rarity_common_weight_label'),
      help: app.translator.trans('donk-aigc-collectibles.admin.settings.rarity_help'),
      min: 0,
      max: 100,
      placeholder: '60',
    }))
    .setting(() => ({
      setting: 'donk-aigc-collectibles.rarity-rare',
      type: 'number',
      label: app.translator.trans('donk-aigc-collectibles.admin.settings.rarity_rare_weight_label'),
      min: 0,
      max: 100,
      placeholder: '25',
    }))
    .setting(() => ({
      setting: 'donk-aigc-collectibles.rarity-epic',
      type: 'number',
      label: app.translator.trans('donk-aigc-collectibles.admin.settings.rarity_epic_weight_label'),
      min: 0,
      max: 100,
      placeholder: '12',
    }))
    .setting(() => ({
      setting: 'donk-aigc-collectibles.rarity-legendary',
      type: 'number',
      label: app.translator.trans('donk-aigc-collectibles.admin.settings.rarity_legendary_weight_label'),
      min: 0,
      max: 100,
      placeholder: '3',
    }))

    // -- WebSocket Settings --
    .customSetting(() => <h3>{app.translator.trans('donk-aigc-collectibles.admin.settings.section_websocket')}</h3>)
    .setting(() => ({
      setting: 'donk-aigc-collectibles.ws-url',
      type: 'text',
      label: app.translator.trans('donk-aigc-collectibles.admin.settings.ws_url_label'),
      help: app.translator.trans('donk-aigc-collectibles.admin.settings.ws_url_help'),
      placeholder: 'ws://localhost:8080',
    })),
];
