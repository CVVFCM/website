import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        url: String,
    };

    /**
     * @type {RTCPeerConnection|null}
     */
    webrtc;

    connect() {
        if (!this.element instanceof HTMLVideoElement) {
            throw new Error('Element is not a video element');
        }

        this.start();
    }

    disconnect() {
        if (this.webrtc) {
            this.webrtc.close();
            this.webrtc = null;
        }
    }

    start() {
        this.webrtc = new RTCPeerConnection({
            iceServers: [{
                urls: ['stun:stun.l.google.com:19302'],
            }],
            sdpSemantics: 'unified-plan',
        });

        this.webrtc.ontrack = event => {
            this.element.srcObject = event.streams[0];
            this.element.play();
        }

        this.webrtc.addTransceiver('video', { direction: 'sendrecv' });

        this.webrtc.onnegotiationneeded = async () => {
            const offer = await this.webrtc.createOffer();
            await this.webrtc.setLocalDescription(offer);

            const res = await fetch(this.urlValue, {
                method: 'POST',
                body: new URLSearchParams({ data: btoa(this.webrtc.localDescription.sdp) }),
            });
            const data = await res.text();
            try {
                await this.webrtc.setRemoteDescription(new RTCSessionDescription({ type: 'answer', sdp: atob(data) }));
            } catch (e) {
                console.warn(e);
            }
        };

        const webrtcSendChannel = this.webrtc.createDataChannel('rtsptowebSendChannel')
        webrtcSendChannel.onopen = () => webrtcSendChannel.send('ping');
        webrtcSendChannel.onclose = (_event) => this.start(this.element, this.urlValue);
    }
}
