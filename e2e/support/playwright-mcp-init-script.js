(function () {
  const accounts = [
    '0xf39Fd6e51aad88F6F4ce6aB8827279cffFb92266',
    '0x70997970C51812dc3A010C7d01b50e0d17dc79C8',
    '0x3C44CdDdB6a900fa2b585dd299e03d12FA4293BC',
  ];
  const defaultAccount = accounts[0];
  const storageKey = 'pw-mock-eth-account';
  const listeners = new Map();

  function normalizeAddress(value) {
    return typeof value === 'string' ? value.toLowerCase() : '';
  }

  function pickAccount(address) {
    const normalizedAddress = normalizeAddress(address);
    return accounts.find((candidate) => normalizeAddress(candidate) === normalizedAddress) || defaultAccount;
  }

  function readSelectedAccount() {
    try {
      return pickAccount(window.localStorage.getItem(storageKey));
    } catch (_error) {
      return defaultAccount;
    }
  }

  function persistSelectedAccount(address) {
    try {
      window.localStorage.setItem(storageKey, address);
    } catch (_error) {
    }
  }

  function emit(eventName, payload) {
    const handlers = listeners.get(eventName);
    if (!handlers) return;

    for (const handler of handlers) {
      handler(payload);
    }
  }

  async function handlePersonalSign(params) {
    const first = params && params[0];
    const second = params && params[1];
    const currentAccount = provider.selectedAddress || defaultAccount;

    const message = typeof first === 'string' && first.startsWith('0x') && normalizeAddress(first) === normalizeAddress(currentAccount)
      ? second
      : first;
    const address = typeof first === 'string' && normalizeAddress(first) === normalizeAddress(currentAccount)
      ? first
      : second;
    const resolvedAddress = pickAccount(address || currentAccount);

    if (typeof message !== 'string' || !message.length) {
      throw new Error('Mock wallet expected a message string for personal_sign.');
    }

    return await window.__pwMockPersonalSign(message, resolvedAddress);
  }

  const provider = {
    isMetaMask: true,
    chainId: '0x7a69',
    networkVersion: '31337',
    selectedAddress: readSelectedAccount(),
    request: async ({ method, params = [] }) => {
      switch (method) {
        case 'eth_requestAccounts':
        case 'eth_accounts': {
          const selected = readSelectedAccount();
          provider.selectedAddress = selected;
          persistSelectedAccount(selected);
          return [selected];
        }
        case 'eth_chainId':
          return provider.chainId;
        case 'net_version':
          return provider.networkVersion;
        case 'wallet_switchEthereumChain': {
          const requestedChainId = params[0] && params[0].chainId;
          if (requestedChainId) {
            provider.chainId = requestedChainId;
            emit('chainChanged', requestedChainId);
          }
          return null;
        }
        case 'wallet_addEthereumChain':
          return null;
        case 'personal_sign':
          return await handlePersonalSign(params);
        default:
          throw new Error('Unsupported mock ethereum method: ' + method);
      }
    },
    on: (eventName, handler) => {
      const handlers = listeners.get(eventName) || new Set();
      handlers.add(handler);
      listeners.set(eventName, handlers);
    },
    removeListener: (eventName, handler) => {
      const handlers = listeners.get(eventName);
      if (!handlers) return;
      handlers.delete(handler);
      if (handlers.size === 0) listeners.delete(eventName);
    },
  };

  window.__pwSelectMockEthereumAccount = function (address) {
    const selected = pickAccount(address);
    provider.selectedAddress = selected;
    persistSelectedAccount(selected);
    emit('accountsChanged', [selected]);
    return selected;
  };

  window.__pwMockEthereumAccounts = accounts.slice();
  window.isPlaywrightMCP = true;

  Object.defineProperty(window, 'ethereum', {
    configurable: true,
    enumerable: true,
    writable: true,
    value: provider,
  });
})();
