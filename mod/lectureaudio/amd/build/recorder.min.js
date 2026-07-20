define(['jquery', 'core/ajax', 'core/notification', 'core/str'], function($, Ajax, Notification, Str) {
    "use strict";

    var Recorder = function(contextId, summaryContent) {
        this.contextId = contextId;
        this.summaryContent = summaryContent;
        
        // Components
        this.mediaRecorder = null;
        this.stream = null;
        this.audioCtx = null;
        this.analyser = null;
        this.source = null;
        
        // State
        this.chunkIndex = 0;
        this.timerInterval = null;
        this.startTime = 0;
        this.visualizerFrame = null;
        // this.audioChunks = []; // DELETED: No longer saving full audio
        
        // Dynamic Chunking
        this.chunkStartTime = 0;
        this.silenceCheckInterval = null;
        this.lastRawSummary = '';
        
        this.init();
    };

    Recorder.prototype.init = function() {
        var self = this;
        $('#startBtn').click(function() { self.startRecording(); });
        $('#stopBtn').click(function() { self.stopRecording(); });
        $('#summarizeBtn').click(function() { self.generateSummary(); });
        $('#uploadBtn').click(function() { self.uploadRecording(); });

        // Render existing summary if available
        if (this.summaryContent) {
            // Restore it to state so we can re-save if needed
            this.lastRawSummary = this.summaryContent;
            
            var html = this.parseMarkdown(this.summaryContent);
            $('#aiSummaryContent').html(html);
            $('#summary-section').show();
            // Also enable upload button if they want to edit transcript and re-save
            $('#uploadBtn').prop('disabled', false);
        }
    };

    Recorder.prototype.startRecording = function() {
        var self = this;
        
        // Reset UI
        $('#liveTranscript').val('');
        $('#summary-section').hide();
        $('#aiSummaryContent').empty();
        
        // Reset State
        this.chunkIndex = 0;
        this.lastRawSummary = ''; // Clear previous summary
        
        // Get Canvas
        this.canvas = document.getElementById('audio-visualizer');
        if (this.canvas) {
            this.canvasCtx = this.canvas.getContext('2d');
        }

        var constraints = { audio: true };

        navigator.mediaDevices.getUserMedia(constraints).then(function(stream) {
            self.stream = stream;

            // 1. Setup Visualizer (requires AudioContext)
            if (!self.audioCtx || self.audioCtx.state === 'closed') {
                self.audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            } else if (self.audioCtx.state === 'suspended') {
                self.audioCtx.resume();
            }
            
            self.source = self.audioCtx.createMediaStreamSource(stream);
            self.analyser = self.audioCtx.createAnalyser();
            self.analyser.fftSize = 2048;
            self.source.connect(self.analyser);
            self.drawVisualizer();

            // 2. Setup MediaRecorder for Chunking
            self.mediaRecorder = new MediaRecorder(stream);
            
            self.mediaRecorder.ondataavailable = function(e) {
                if (e.data && e.data.size > 0) {
                    // self.audioChunks.push(e.data); // DELETED: Not saving audio
                    self.processChunk(e.data);
                }
            };

            // Start with continuous recording
            self.mediaRecorder.start(); 
            self.chunkStartTime = Date.now();
            
            // Start Silence Detector Loop (Check every 200ms)
            self.silenceCheckInterval = setInterval(function() {
                self.checkSilenceAndCut();
            }, 200);

            // Timer
            self.startTimer();

            // UI Updates
            $('#startBtn').prop('disabled', true);
            $('#stopBtn').prop('disabled', false);
            $('#summarizeBtn').prop('disabled', true);
            $('#verifyBtn').prop('disabled', true).hide(); // Not used in this mode
            $('#uploadBtn').prop('disabled', true);
            $('#statusMsg').text('Recording... (60s chunks)');

        }).catch(function(err) {
            Notification.exception(err);
        });
    };

    Recorder.prototype.checkSilenceAndCut = function() {
        if (!this.analyser) return;

        var bufferLength = this.analyser.fftSize;
        var dataArray = new Uint8Array(bufferLength);
        this.analyser.getByteTimeDomainData(dataArray);

        // Calculate RMS to detect silence
        // Values are 0-255, Silence is ~128
        var sum = 0;
        for(var i = 0; i < bufferLength; i++) {
            var x = dataArray[i] - 128;
            sum += x * x;
        }
        var rms = Math.sqrt(sum / bufferLength);

        var elapsed = Date.now() - this.chunkStartTime;
        var MIN_CHUNK_TIME = 60 * 1000; // 60 seconds
        var MAX_CHUNK_TIME = MIN_CHUNK_TIME + 15000; // Force cut after 75s
        var SILENCE_THRESHOLD = 5; // Low RMS = Silence

        // Cut logic: Wait for min time, then look for silence OR max time limit
        if (elapsed > MIN_CHUNK_TIME) {
            if (rms < SILENCE_THRESHOLD || elapsed > MAX_CHUNK_TIME) {
                // Request a new blob. This triggers ondataavailable
                this.mediaRecorder.requestData(); 
                this.chunkStartTime = Date.now();
                $('#statusMsg').text('Sent chunk to AI...');
            }
        }
    };

    Recorder.prototype.processChunk = function(blob) {
        var self = this;
        var formData = new FormData();
        // Server expects 'file' and 'chunk_index' matches python script
        formData.append('file', blob, 'chunk.wav'); 
        formData.append('chunk_index', self.chunkIndex);
        self.chunkIndex++;

        // Send to Python Server
        fetch('http://localhost:8000/transcribe_chunk', {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.text) {
                var box = $('#liveTranscript');
                var current = box.val();
                box.val(current + data.text + " ");
                // Auto-scroll
                box.scrollTop(box[0].scrollHeight);
            }
        })
        .catch(function(err) {
            console.error("Transcription Error:", err);
            $('#statusMsg').text('Transcription connection failed (localhost:8000).');
        });
    };

    Recorder.prototype.stopRecording = function() {
        var self = this;
        
        if (this.mediaRecorder && this.mediaRecorder.state !== 'inactive') {
            this.mediaRecorder.stop();
        }

        // Stop Media Streams
        if (this.stream) {
            this.stream.getTracks().forEach(function(track) { track.stop(); });
        }
        
        // Stop Animations/Timers
        if (this.timerInterval) clearInterval(this.timerInterval);
        if (this.silenceCheckInterval) clearInterval(this.silenceCheckInterval);
        if (this.visualizerFrame) cancelAnimationFrame(this.visualizerFrame);

        $('#stopBtn').prop('disabled', true);
        $('#startBtn').prop('disabled', false).text('Start New Recording');
        $('#summarizeBtn').prop('disabled', false); // Enable summarize
        $('#uploadBtn').prop('disabled', false); // Enable save button manual
        $('#statusMsg').text('Recording Stopped. Click "Summarize" or "Save/Upload".');
    };

    Recorder.prototype.generateSummary = function() {
        var self = this;
        var text = $('#liveTranscript').val();
        if (!text || text.length < 10) {
            $('#statusMsg').text('Transcript too short to summarize.');
            return;
        }

        $('#statusMsg').text('Generating AI Summary... please wait.');
        $('#summarizeBtn').prop('disabled', true);
        $('#uploadBtn').prop('disabled', true);

        fetch('http://localhost:8000/summarize', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ text: text })
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.summary) {
                self.lastRawSummary = data.summary; // Store for upload
                $('#summary-section').fadeIn();
                var html = self.parseMarkdown(data.summary);
                $('#aiSummaryContent').html(html);
                
                // Scroll to summary
                $('html, body').animate({
                    scrollTop: $("#summary-section").offset().top - 50
                }, 800);

                $('#statusMsg').text('Summary generated successfully!');
            } else if (data.error) {
                $('#statusMsg').text('Summarization failed: ' + data.error);
            }
        })
        .catch(function(err) {
            console.error(err);
            $('#statusMsg').text('Failed to connect to Summarizer.');
        })
        .finally(function() {
            $('#summarizeBtn').prop('disabled', false);
            $('#uploadBtn').prop('disabled', false);
        });
    };
    
    // Simple Markdown Parser with Code Block Support
    Recorder.prototype.parseMarkdown = function(markdown) {
        if (!markdown) return '';
        
        // Split by code blocks
        var parts = markdown.split(/(```[\s\S]*?```)/g);
        var html = "";

        parts.forEach(function(part) {
            if (part.match(/^```/)) {
                // Code block processing
                // Remove first line (```lang) and last line (```)
                var content = part.replace(/^```[a-z]*\n?/im, '').replace(/```$/, '');
                // Escape HTML inside code
                content = content.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
                html += '<div class="alert alert-dark my-3"><pre class="m-0 text-white"><code>' + content + '</code></pre></div>';
            } else {
                // Normal markdown processing
                var processed = part
                    .replace(/^### (.*$)/gim, '<h3 class="mt-3">$1</h3>')
                    .replace(/^## (.*$)/gim, '<h2 class="mt-4">$1</h2>')
                    .replace(/^# (.*$)/gim, '<h1 class="mt-5">$1</h1>')
                    .replace(/\*\*(.*)\*\*/gim, '<b>$1</b>')
                    .replace(/\*(.*)\*/gim, '<i>$1</i>')
                    .replace(/^- (.*$)/gim, '<ul><li class="ml-4">$1</li></ul>')
                    .replace(/\n\n/gim, '<p></p>')
                    .replace(/\n/gim, '<br>');
                html += processed;
            }
        });
        
        return html;
    };

    Recorder.prototype.uploadRecording = function() {
        var self = this;
        var text = $('#liveTranscript').val();
         // Attempt to get the raw summary text back if possible, or just send empty if not generated.
        var summary = self.lastRawSummary || '';
        
        console.log("Uploading Transcript (len=" + text.length + ") and Summary (len=" + summary.length + ")");

        $('#statusMsg').text('Saving Data...');

        Ajax.call([{
            methodname: 'mod_lectureaudio_upload_recording',
            args: {
                contextid: self.contextId,
                filecontent: '', // No audio
                transcript: text,
                summary: summary
            }
        }])[0].done(function(response) {
            console.log("Upload Success", response);
            $('#statusMsg').text('Reference materials saved!');
            // Delay reload slightly to ensure user sees message? No, immediate is fine.
            window.location.reload();
        }).fail(function(ex) {
            console.error("Upload Failed", ex);
            $('#statusMsg').text('Save failed: ' + ex.message);
            Notification.exception(ex);
        });
    };

    Recorder.prototype.drawVisualizer = function() {
        var self = this;
        var WIDTH = this.canvas.width;
        var HEIGHT = this.canvas.height;
        var bufferLength = this.analyser.frequencyBinCount;
        var dataArray = new Uint8Array(bufferLength);
        
        var draw = function() {
            if (!self.canvasCtx) return;
            
            self.visualizerFrame = requestAnimationFrame(draw);
            self.analyser.getByteTimeDomainData(dataArray);
            
            self.canvasCtx.fillStyle = '#f8f9fa';
            self.canvasCtx.fillRect(0, 0, WIDTH, HEIGHT);
            self.canvasCtx.lineWidth = 2;
            self.canvasCtx.strokeStyle = '#dc3545';
            self.canvasCtx.beginPath();
            
            var sliceWidth = WIDTH * 1.0 / bufferLength;
            var x = 0;
            
            for(var i = 0; i < bufferLength; i++) {
                var v = dataArray[i] / 128.0;
                var y = v * HEIGHT / 2;
                if(i === 0) {
                    self.canvasCtx.moveTo(x, y);
                } else {
                    self.canvasCtx.lineTo(x, y);
                }
                x += sliceWidth;
            }
            self.canvasCtx.lineTo(self.canvas.width, self.canvas.height/2);
            self.canvasCtx.stroke();
        };
        draw();
    };

    Recorder.prototype.startTimer = function() {
        var self = this;
        this.startTime = Date.now();
        var timerDisplay = $('#timer');
        timerDisplay.text("00:00");
        this.timerInterval = setInterval(function() {
            var diff = Date.now() - self.startTime;
            var seconds = Math.floor((diff / 1000) % 60);
            var minutes = Math.floor((diff / (1000 * 60)) % 60);
            seconds = (seconds < 10) ? "0" + seconds : seconds;
            minutes = (minutes < 10) ? "0" + minutes : minutes;
            timerDisplay.text(minutes + ":" + seconds);
        }, 1000);
    };

    return {
        init: function(config) {
            new Recorder(config.contextid, config.summaryContent);
        }
    };
});
