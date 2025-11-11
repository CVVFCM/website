import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        url: String,
    };

    mseStreamingStarted = false;

    /**
     * @type {MediaSource}
     */
    mse;

    /**
     * @type {SourceBuffer}
     */
    mseSourceBuffer = null;

    mseQueue = [];

    connect() {
        if (!this.element instanceof HTMLVideoElement) {
            throw new Error('Element is not a video element');
        }

        this.start();
    }

    start() {
        this.mse = new MediaSource();
        this.element.src = window.URL.createObjectURL(this.mse)

        this.mse.addEventListener('sourceopen', () => {
            const ws = new WebSocket(this.urlValue);

            ws.binaryType = 'arraybuffer';
            ws.onmessage = (event) => {
                const data = new Uint8Array(event.data);
                if (data[0] === 9) {
                    let mimeCodec;
                    const decodedArr = data.slice(1);

                    if (window.TextDecoder) {
                        mimeCodec = new TextDecoder('utf-8').decode(decodedArr);
                    } else {
                        mimeCodec = Utf8ArrayToStr(decodedArr);
                    }

                    this.mseSourceBuffer = this.mse.addSourceBuffer('video/mp4; codecs="' + mimeCodec + '"');
                    this.mseSourceBuffer.mode = 'segments';
                    this.mseSourceBuffer.addEventListener('updateend', this.pushPacket.bind(this));
                } else {
                    this.readPacket(event.data);
                }
            }
        }, false);
    }

    pushPacket() {
        let packet;

        if (!this.mseSourceBuffer.updating) {
            if (this.mseQueue.length > 0) {
                packet = this.mseQueue.shift();

                this.mseSourceBuffer.appendBuffer(packet);
            } else {
                this.mseStreamingStarted = false;
            }
        }
    }

    readPacket (packet) {
        if (!this.mseStreamingStarted) {
            this.mseSourceBuffer.appendBuffer(packet);
            this.mseStreamingStarted = true;

            return;
        }

        this.mseQueue.push(packet);
        if (!this.mseSourceBuffer.updating) {
            this.pushPacket();
        }
    }
}
