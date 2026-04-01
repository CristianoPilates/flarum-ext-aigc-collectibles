import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import { isMetaMaskAvailable, connectWallet, signMessage, truncateAddress } from '../utils/web3';

interface WalletConnectorAttrs {
  user: any;
}

export default class WalletConnector extends Component<WalletConnectorAttrs> {
  loading: boolean = false;
  walletAddress: string | null = null;
  walletId: string | null = null;
  error: string | null = null;
  step: 'idle' | 'connecting' | 'signing' | 'verifying' = 'idle';

  oninit(vnode: any) {
    super.oninit(vnode);
    this.loading = false;
    this.error = null;
    this.step = 'idle';
    this.loadBoundWallet();
  }

  view() {
    const user = app.session?.user;
    if (!user) return null;

    const isOwnProfile = this.attrs.user && this.attrs.user.id() === user.id();
    if (!isOwnProfile && !this.walletAddress) return null;

    return (
      <div className="WalletConnector">
        <h3 className="WalletConnector-title">
          <i className="fab fa-ethereum" />{' '}
          {app.translator.trans('donk-aigc-collectibles.forum.wallet.title')}
        </h3>

        {this.error && (
          <div className="WalletConnector-error">
            <p>{this.error}</p>
          </div>
        )}

        {this.walletAddress ? this.viewBound() : isOwnProfile ? this.viewUnbound() : null}
      </div>
    );
  }

  viewBound() {
    const isOwn = this.attrs.user && app.session?.user && this.attrs.user.id() === app.session?.user.id();

    return (
      <div className="WalletConnector-bound">
        <div className="WalletConnector-address">
          <i className="fas fa-wallet" />
          <code>{truncateAddress(this.walletAddress!)}</code>
        </div>
        {isOwn && (
          <Button
            className="Button Button--danger Button--small"
            onclick={() => this.unbindWallet()}
            loading={this.loading}
            icon="fas fa-unlink"
          >
            {app.translator.trans('donk-aigc-collectibles.forum.wallet.unbind')}
          </Button>
        )}
      </div>
    );
  }

  viewUnbound() {
    if (!isMetaMaskAvailable()) {
      return (
        <div className="WalletConnector-noMetaMask">
          <p>{app.translator.trans('donk-aigc-collectibles.forum.wallet.install_metamask')}</p>
        </div>
      );
    }

    const stepLabels: Record<string, string> = {
      connecting: String(app.translator.trans('donk-aigc-collectibles.forum.wallet.step_connecting')),
      signing: String(app.translator.trans('donk-aigc-collectibles.forum.wallet.step_signing')),
      verifying: String(app.translator.trans('donk-aigc-collectibles.forum.wallet.step_verifying')),
    };

    return (
      <div className="WalletConnector-connect">
        {this.step !== 'idle' && (
          <div className="WalletConnector-step">
            <LoadingIndicator size="small" />
            <span>{stepLabels[this.step]}</span>
          </div>
        )}
        <Button
          className="Button Button--primary"
          onclick={() => this.bindWallet()}
          loading={this.loading}
          disabled={this.loading}
          icon="fab fa-ethereum"
        >
          {app.translator.trans('donk-aigc-collectibles.forum.wallet.connect')}
        </Button>
      </div>
    );
  }

  loadBoundWallet() {
    const user = this.attrs.user;
    if (!user) return;

    // Check if user has web3 account attributes from the API
    const web3Address = user.attribute('web3Address') as string | null;
    if (web3Address) {
      this.walletAddress = web3Address;
      this.walletId = (user.attribute('web3AccountId') as string | null) || null;
    }
  }

  async bindWallet() {
    if (this.loading) return;

    this.loading = true;
    this.error = null;

    try {
      // Step 1: Connect MetaMask
      this.step = 'connecting';
      m.redraw();
      const address = await connectWallet();

      // Step 2: Request nonce from server
      this.step = 'signing';
      m.redraw();

      const nonceResponse: any = await app.request({
        method: 'POST',
        url: app.forum.attribute('apiUrl') + '/web3-accounts/nonce',
        body: {
          data: {
            type: 'web3-nonce',
            attributes: { address },
          },
        },
      });

      const nonce = nonceResponse.data?.attributes?.nonce;
      const messageToSign = nonceResponse.data?.attributes?.message;

      // Step 3: Sign message with MetaMask
      const signature = await signMessage(messageToSign);

      // Step 4: Verify on server
      this.step = 'verifying';
      m.redraw();

      const verifyResponse: any = await app.request({
        method: 'POST',
        url: app.forum.attribute('apiUrl') + '/web3-accounts',
        body: {
          data: {
            type: 'web3-accounts',
            attributes: {
              address,
              signature,
              nonce,
            },
          },
        },
      });

      this.walletAddress = address;
      this.walletId = verifyResponse?.data?.id || null;
      this.step = 'idle';
      this.loading = false;

      // Update user attributes
      const user = app.session?.user;
      if (user) {
        user.pushAttributes({ web3Address: address, web3AccountId: this.walletId });
      }

      m.redraw();
    } catch (error: any) {
      this.step = 'idle';
      this.loading = false;

      if (error.message) {
        this.error = error.message;
      } else if (error.response?.errors?.[0]?.detail) {
        this.error = error.response.errors[0].detail;
      } else {
        this.error = String(app.translator.trans('donk-aigc-collectibles.forum.wallet.bind_failed'));
      }

      m.redraw();
    }
  }

  unbindWallet() {
    if (this.loading || !this.walletId) return;

    this.loading = true;

    app
      .request({
        method: 'DELETE',
        url: app.forum.attribute('apiUrl') + '/web3-accounts/' + this.walletId,
      })
      .then(() => {
        this.walletAddress = null;
        this.walletId = null;
        this.loading = false;

        const user = app.session?.user;
        if (user) {
          user.pushAttributes({ web3Address: null, web3AccountId: null });
        }

        m.redraw();
      })
      .catch((error: any) => {
        this.loading = false;
        this.error =
          error.response?.errors?.[0]?.detail ||
          String(app.translator.trans('donk-aigc-collectibles.forum.wallet.unbind_failed'));
        m.redraw();
      });
  }
}
