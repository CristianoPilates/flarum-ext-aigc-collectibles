import app from 'flarum/admin/app';

app.initializers.add('donk-aigc-collectibles', () => {
  app.extensionData
    .for('donk-aigc-collectibles')

    // -- Check-in Settings --
    .registerSetting({
      setting: 'donk-aigc-collectibles.checkin-reward',
      type: 'number',
      label: app.translator.trans('donk-aigc-collectibles.admin.settings.checkin_reward_label'),
      help: app.translator.trans('donk-aigc-collectibles.admin.settings.checkin_reward_help'),
      min: 1,
      placeholder: '1',
    })

    // -- AIGC API Settings --
    .registerSetting({
      setting: 'donk-aigc-collectibles.aigc-enabled',
      type: 'boolean',
      label: app.translator.trans('donk-aigc-collectibles.admin.settings.aigc_enabled_label'),
      help: app.translator.trans('donk-aigc-collectibles.admin.settings.aigc_enabled_help'),
    })
    .registerSetting({
      setting: 'donk-aigc-collectibles.aigc-api-url',
      type: 'text',
      label: app.translator.trans('donk-aigc-collectibles.admin.settings.aigc_endpoint_label'),
      placeholder: 'https://api.openai.com/v1/images/generations',
    })
    .registerSetting({
      setting: 'donk-aigc-collectibles.aigc-api-key',
      type: 'password',
      label: app.translator.trans('donk-aigc-collectibles.admin.settings.aigc_api_key_label'),
    })
    .registerSetting({
      setting: 'donk-aigc-collectibles.aigc-model',
      type: 'text',
      label: app.translator.trans('donk-aigc-collectibles.admin.settings.aigc_model_label'),
      placeholder: 'dall-e-3',
    })
    .registerSetting({
      setting: 'donk-aigc-collectibles.aigc-base-prompt',
      type: 'textarea',
      label: app.translator.trans('donk-aigc-collectibles.admin.settings.aigc_prompt_template_label'),
      help: app.translator.trans('donk-aigc-collectibles.admin.settings.aigc_prompt_template_help'),
      placeholder: 'A beautiful digital artwork of a {rarity} collectible, fantasy style, vibrant colors',
    })

    // -- IPFS Settings --
    .registerSetting({
      setting: 'donk-aigc-collectibles.ipfs-api-url',
      type: 'text',
      label: app.translator.trans('donk-aigc-collectibles.admin.settings.ipfs_endpoint_label'),
      placeholder: 'https://api.pinata.cloud',
    })
    .registerSetting({
      setting: 'donk-aigc-collectibles.ipfs-api-key',
      type: 'password',
      label: app.translator.trans('donk-aigc-collectibles.admin.settings.ipfs_api_key_label'),
    })
    .registerSetting({
      setting: 'donk-aigc-collectibles.ipfs-gateway-url',
      type: 'text',
      label: app.translator.trans('donk-aigc-collectibles.admin.settings.ipfs_gateway_label'),
      placeholder: 'https://ipfs.io/ipfs/',
    })

    // -- Blockchain Settings --
    .registerSetting({
      setting: 'donk-aigc-collectibles.blockchain-enabled',
      type: 'boolean',
      label: app.translator.trans('donk-aigc-collectibles.admin.settings.blockchain_enabled_label'),
      help: app.translator.trans('donk-aigc-collectibles.admin.settings.blockchain_enabled_help'),
    })
    .registerSetting({
      setting: 'donk-aigc-collectibles.blockchain-rpc-url',
      type: 'text',
      label: app.translator.trans('donk-aigc-collectibles.admin.settings.blockchain_rpc_url_label'),
      placeholder: 'http://127.0.0.1:8545',
    })
    .registerSetting({
      setting: 'donk-aigc-collectibles.nft-contract-address',
      type: 'text',
      label: app.translator.trans('donk-aigc-collectibles.admin.settings.blockchain_contract_address_label'),
      placeholder: '0x...',
    })
    .registerSetting({
      setting: 'donk-aigc-collectibles.minter-private-key',
      type: 'password',
      label: app.translator.trans('donk-aigc-collectibles.admin.settings.blockchain_private_key_label'),
      help: app.translator.trans('donk-aigc-collectibles.admin.settings.blockchain_private_key_help'),
    })

    // -- Rarity Weights --
    .registerSetting({
      setting: 'donk-aigc-collectibles.rarity-common',
      type: 'number',
      label: app.translator.trans('donk-aigc-collectibles.admin.settings.rarity_common_label'),
      help: app.translator.trans('donk-aigc-collectibles.admin.settings.rarity_weights_help'),
      min: 0,
      max: 100,
      placeholder: '60',
    })
    .registerSetting({
      setting: 'donk-aigc-collectibles.rarity-rare',
      type: 'number',
      label: app.translator.trans('donk-aigc-collectibles.admin.settings.rarity_rare_label'),
      min: 0,
      max: 100,
      placeholder: '25',
    })
    .registerSetting({
      setting: 'donk-aigc-collectibles.rarity-epic',
      type: 'number',
      label: app.translator.trans('donk-aigc-collectibles.admin.settings.rarity_epic_label'),
      min: 0,
      max: 100,
      placeholder: '12',
    })
    .registerSetting({
      setting: 'donk-aigc-collectibles.rarity-legendary',
      type: 'number',
      label: app.translator.trans('donk-aigc-collectibles.admin.settings.rarity_legendary_label'),
      min: 0,
      max: 100,
      placeholder: '3',
    })

    // -- WebSocket Settings --
    .registerSetting({
      setting: 'donk-aigc-collectibles.ws-url',
      type: 'text',
      label: app.translator.trans('donk-aigc-collectibles.admin.settings.ws_url_label'),
      help: app.translator.trans('donk-aigc-collectibles.admin.settings.ws_url_help'),
      placeholder: 'ws://localhost:8080',
    });
});
