declare global {
  interface Window {
    ethereum?: {
      isMetaMask?: boolean;
      request(args: { method: string; params?: any[] }): Promise<any>;
      on(event: string, handler: (...args: any[]) => void): void;
      removeListener(event: string, handler: (...args: any[]) => void): void;
    };
  }
}

export function isMetaMaskAvailable(): boolean {
  return typeof window.ethereum !== 'undefined' && !!window.ethereum.isMetaMask;
}

export async function connectWallet(): Promise<string> {
  if (!window.ethereum) {
    throw new Error('MetaMask is not installed');
  }

  const accounts: string[] = await window.ethereum.request({
    method: 'eth_requestAccounts',
  });

  if (!accounts || accounts.length === 0) {
    throw new Error('No accounts returned');
  }

  return accounts[0];
}

export async function signMessage(message: string): Promise<string> {
  if (!window.ethereum) {
    throw new Error('MetaMask is not installed');
  }

  const accounts: string[] = await window.ethereum.request({
    method: 'eth_accounts',
  });

  if (!accounts || accounts.length === 0) {
    throw new Error('No connected account');
  }

  const signature: string = await window.ethereum.request({
    method: 'personal_sign',
    params: [message, accounts[0]],
  });

  return signature;
}

export function truncateAddress(address: string): string {
  if (!address || address.length < 10) return address;
  return address.slice(0, 6) + '...' + address.slice(-4);
}
