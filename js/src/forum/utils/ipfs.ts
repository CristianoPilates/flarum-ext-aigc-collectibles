import app from 'flarum/forum/app';

export function gatewayUrl(cid: string): string {
  if (!cid) return '';

  const gateway = app.forum.attribute<string>('donk-aigc-collectibles.ipfs-gateway-url') || 'https://ipfs.io/ipfs/';

  // Normalize: strip ipfs:// prefix if present
  const cleanCid = cid.replace(/^ipfs:\/\//, '');

  // Ensure gateway ends with /
  const base = gateway.endsWith('/') ? gateway : gateway + '/';

  return base + cleanCid;
}

export function ipfsUri(cid: string): string {
  if (!cid) return '';
  const cleanCid = cid.replace(/^ipfs:\/\//, '');
  return 'ipfs://' + cleanCid;
}
