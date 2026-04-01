import app from 'flarum/forum/app';
import type Mithril from 'mithril';
import type { IInternalModalAttrs } from 'flarum/common/components/Modal';
import Modal from 'flarum/common/components/Modal';
import Link from 'flarum/common/components/Link';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';

interface CollectibleProofModalAttrs extends IInternalModalAttrs {
  collectible: any;
}

interface ProofState {
  app?: any;
  chain?: any;
  metadata?: any;
  image?: any;
}

export default class CollectibleProofModal extends Modal<CollectibleProofModalAttrs> {
  loading: boolean = true;
  error: string | null = null;
  proof: ProofState | null = null;

  oninit(vnode: any) {
    super.oninit(vnode);
    this.loading = true;
    this.error = null;
    this.proof = null;
    this.loadProof();
  }

  className() {
    return 'CollectibleProofModal Modal--large';
  }

  title() {
    return app.translator.trans('donk-aigc-collectibles.forum.collectible.proof_title');
  }

  content() {
    if (this.loading) {
      return (
        <div className="Modal-body CollectibleProofModal-body">
          <div className="CollectibleProofModal-loading">
            <LoadingIndicator />
          </div>
        </div>
      );
    }

    if (this.error) {
      return (
        <div className="Modal-body CollectibleProofModal-body">
          <div className="CollectibleProofModal-error">{this.error}</div>
        </div>
      );
    }

    if (!this.proof) {
      return null;
    }

    const proof = this.proof;

    return (
      <div className="Modal-body CollectibleProofModal-body">
        <div className="CollectibleProofModal-grid">
          {this.renderSection(
            app.translator.trans('donk-aigc-collectibles.forum.collectible.proof_section_app'),
            [
              this.renderRow(app.translator.trans('donk-aigc-collectibles.forum.collectible.proof_field_collectible'), proof.app?.name || '-'),
              this.renderRow(
                app.translator.trans('donk-aigc-collectibles.forum.collectible.proof_field_owner'),
                this.renderOwnerLink(proof.app)
              ),
              this.renderRow(app.translator.trans('donk-aigc-collectibles.forum.collectible.proof_field_token_id'), this.renderMaybeValue(proof.app?.tokenId)),
              this.renderRow(app.translator.trans('donk-aigc-collectibles.forum.collectible.proof_field_metadata_cid'), this.renderMaybeValue(proof.app?.metadataCid)),
              this.renderRow(app.translator.trans('donk-aigc-collectibles.forum.collectible.proof_field_image_cid'), this.renderMaybeValue(proof.app?.ipfsCid)),
              this.renderRow(app.translator.trans('donk-aigc-collectibles.forum.collectible.proof_field_expected_token_uri'), this.renderMaybeValue(proof.app?.expectedTokenUri)),
              this.renderLinkRow(
                app.translator.trans('donk-aigc-collectibles.forum.collectible.proof_open_metadata'),
                proof.app?.metadataGatewayUrl
              ),
              this.renderLinkRow(
                app.translator.trans('donk-aigc-collectibles.forum.collectible.proof_open_image'),
                proof.app?.imageGatewayUrl
              ),
            ]
          )}

          {this.renderSection(
            app.translator.trans('donk-aigc-collectibles.forum.collectible.proof_section_chain'),
            proof.chain?.available
              ? [
                  this.renderRow(app.translator.trans('donk-aigc-collectibles.forum.collectible.proof_field_chain_id'), this.renderMaybeValue(proof.chain?.chainId)),
                  this.renderRow(app.translator.trans('donk-aigc-collectibles.forum.collectible.proof_field_contract'), this.renderMaybeValue(proof.chain?.contractAddress)),
                  this.renderRow(app.translator.trans('donk-aigc-collectibles.forum.collectible.proof_field_owner_of'), this.renderMaybeValue(proof.chain?.ownerOf)),
                  this.renderRow(app.translator.trans('donk-aigc-collectibles.forum.collectible.proof_field_token_uri'), this.renderMaybeValue(proof.chain?.tokenURI)),
                  this.renderRow(
                    app.translator.trans('donk-aigc-collectibles.forum.collectible.proof_match_metadata'),
                    this.renderBoolean(proof.chain?.matchesMetadataCid)
                  ),
                  this.renderLinkRow(
                    app.translator.trans('donk-aigc-collectibles.forum.collectible.proof_open_token_uri'),
                    proof.chain?.tokenUriGatewayUrl
                  ),
                ]
              : [this.renderState(proof.chain)]
          )}

          {this.renderSection(
            app.translator.trans('donk-aigc-collectibles.forum.collectible.proof_section_metadata'),
            proof.metadata?.available
              ? [
                  this.renderRow(app.translator.trans('donk-aigc-collectibles.forum.collectible.proof_field_cid'), this.renderMaybeValue(proof.metadata?.cid)),
                  this.renderRow(app.translator.trans('donk-aigc-collectibles.forum.collectible.proof_field_image_field'), this.renderMaybeValue(proof.metadata?.imageField)),
                  this.renderRow(app.translator.trans('donk-aigc-collectibles.forum.collectible.proof_field_image_cid'), this.renderMaybeValue(proof.metadata?.imageCid)),
                  this.renderJson(proof.metadata?.json),
                  this.renderLinkRow(
                    app.translator.trans('donk-aigc-collectibles.forum.collectible.proof_open_metadata'),
                    proof.metadata?.gatewayUrl
                  ),
                ]
              : [this.renderState(proof.metadata)]
          )}

          {this.renderSection(
            app.translator.trans('donk-aigc-collectibles.forum.collectible.proof_section_image'),
            proof.image?.available
              ? [
                  this.renderRow(app.translator.trans('donk-aigc-collectibles.forum.collectible.proof_field_cid'), this.renderMaybeValue(proof.image?.cid)),
                  this.renderRow(app.translator.trans('donk-aigc-collectibles.forum.collectible.proof_field_source'), this.renderMaybeValue(proof.image?.source)),
                  this.renderRow(
                    app.translator.trans('donk-aigc-collectibles.forum.collectible.proof_match_metadata_image'),
                    this.renderBoolean(proof.image?.matchesMetadataImage)
                  ),
                  this.renderLinkRow(
                    app.translator.trans('donk-aigc-collectibles.forum.collectible.proof_open_image'),
                    proof.image?.gatewayUrl
                  ),
                ]
              : [this.renderState(proof.image)]
          )}
        </div>
      </div>
    );
  }

  async loadProof() {
    const collectible = this.attrs.collectible;
    if (!collectible) {
      this.error = 'Collectible is required.';
      this.loading = false;
      m.redraw();
      return;
    }

    try {
      const response: any = await app.request({
        method: 'GET',
        url: app.forum.attribute('apiUrl') + '/collectibles/' + collectible.id() + '/proof',
      });

      this.proof = response?.data?.attributes || null;
    } catch (error: any) {
      this.error = error.response?.errors?.[0]?.detail || 'Failed to load collectible proof.';
    } finally {
      this.loading = false;
      m.redraw();
    }
  }

  renderSection(title: Mithril.Children, children: Mithril.Children) {
    return (
      <section className="CollectibleProofModal-section">
        <h3 className="CollectibleProofModal-sectionTitle">{title}</h3>
        <div className="CollectibleProofModal-sectionBody">{children}</div>
      </section>
    );
  }

  renderRow(label: Mithril.Children, value: Mithril.Children) {
    return (
      <div className="CollectibleProofModal-row">
        <span className="CollectibleProofModal-label">{label}</span>
        <span className="CollectibleProofModal-value">{value}</span>
      </div>
    );
  }

  renderLinkRow(label: Mithril.Children, href?: string | null) {
    if (!href) return null;

    return this.renderRow(
      label,
      <a href={href} target="_blank" rel="noreferrer">
        {href}
      </a>
    );
  }

  renderOwnerLink(appLayer?: any) {
    const ownerUsername = appLayer?.ownerUsername;
    const ownerSlug = appLayer?.ownerSlug || ownerUsername;

    if (!ownerUsername) {
      return '-';
    }

    if (ownerSlug) {
      return <Link href={app.route('user', { username: ownerSlug })}>{ownerUsername}</Link>;
    }

    return ownerUsername;
  }

  renderJson(value: any) {
    if (!value) return null;

    return (
      <pre className="CollectibleProofModal-json">
        {JSON.stringify(value, null, 2)}
      </pre>
    );
  }

  renderState(layer: any) {
    return (
      <div className="CollectibleProofModal-state">
        <div>{this.renderMaybeValue(layer?.state)}</div>
        {layer?.error ? <div>{layer.error}</div> : null}
      </div>
    );
  }

  renderMaybeValue(value: any) {
    if (value === null || value === undefined || value === '') {
      return '-';
    }

    return String(value);
  }

  renderBoolean(value: boolean | null | undefined) {
    if (value === null || value === undefined) {
      return '-';
    }

    return value
      ? app.translator.trans('donk-aigc-collectibles.forum.collectible.proof_yes')
      : app.translator.trans('donk-aigc-collectibles.forum.collectible.proof_no');
  }
}
