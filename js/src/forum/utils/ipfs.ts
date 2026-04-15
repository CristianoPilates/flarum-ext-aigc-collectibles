import app from 'flarum/forum/app';

export function gatewayUrl(cid: string): string {
  if (!cid) return '';

  const gateway = app.forum.attribute<string>('donk-aigc-collectibles.ipfs-gateway-url') || 'https://ipfs.io/ipfs/';

  // Normalize: strip ipfs:// prefix if present
  const cleanCid = cid.replace(/^ipfs:\/\//, '');

  // Support both path-style gateways (`.../ipfs/`) and query-style
  // gateways (`.../api/v0/cat?arg=`), plus explicit `{cid}` templates.
  if (gateway.includes('{cid}')) {
    return gateway.replace(/\{cid\}/g, encodeURIComponent(cleanCid));
  }

  if (gateway.includes('?')) {
    return gateway + encodeURIComponent(cleanCid);
  }

  const base = gateway.endsWith('/') ? gateway : gateway + '/';

  return base + cleanCid;
}

export function ipfsUri(cid: string): string {
  if (!cid) return '';
  const cleanCid = cid.replace(/^ipfs:\/\//, '');
  return 'ipfs://' + cleanCid;
}
