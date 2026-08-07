{{--
    Webcam Capture Component for Filament v3 ViewField
    --------------------------------------------------
    Location: resources/views/forms/components/webcam-capture.blade.php

    This component uses HTML5 <video> + <canvas> to capture a live
    webcam photo as a base64 JPEG string. The string is written to
    the Livewire component state via $wire.set() so Filament can
    process it during form submission.

    Livewire v4 Integration:
    - `$wire.set(statePath, base64String)` writes to the Filament
      form state so the parent component can decode and save it.
    - The statePath is injected by Filament's ViewField via
      `$getStatePath()`.

    Usage in Filament Resource form:
        Forms\Components\ViewField::make('employee_photo')
            ->view('forms.components.webcam-capture')
--}}

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            streaming: false,
            captured: false,
            photoData: @js($getState() ?? ''),

            async startCamera() {
                try {
                    const stream = await navigator.mediaDevices.getUserMedia({
                        video: { width: 640, height: 480, facingMode: 'user' },
                        audio: false
                    });
                    this.$refs.video.srcObject = stream;
                    this.streaming = true;
                    this.captured = false;
                } catch (err) {
                    console.error('Webcam access denied:', err);
                    alert('Unable to access webcam. Please grant camera permission.');
                }
            },

            capturePhoto() {
                const video = this.$refs.video;
                const canvas = this.$refs.canvas;
                const ctx = canvas.getContext('2d');

                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                ctx.drawImage(video, 0, 0);

                // Convert to base64 JPEG (quality 0.85)
                this.photoData = canvas.toDataURL('image/jpeg', 0.85);
                this.captured = true;

                // Stop the video stream
                const tracks = video.srcObject?.getTracks();
                tracks?.forEach(track => track.stop());
                this.streaming = false;

                // Write base64 string to Livewire/Filament state
                $wire.set('{{ $getStatePath() }}', this.photoData);
            },

            retake() {
                this.photoData = '';
                this.captured = false;
                $wire.set('{{ $getStatePath() }}', '');
                this.startCamera();
            },

            removePhoto() {
                this.photoData = '';
                this.captured = false;
                this.streaming = false;
                $wire.set('{{ $getStatePath() }}', '');
            }
        }"
        x-init="
            {{-- If there's already a photo (edit mode), show it --}}
            if (photoData && photoData.length > 0) {
                captured = true;
            }
        "
        class="space-y-4"
    >
        {{-- Video Feed --}}
        <div
            x-show="streaming && !captured"
            x-transition
            class="relative rounded-xl overflow-hidden border-2 border-gray-200 dark:border-gray-700 bg-black"
            style="max-width: 480px;"
        >
            <video
                x-ref="video"
                autoplay
                playsinline
                muted
                class="w-full rounded-xl"
            ></video>

            {{-- Capture Button (overlay) --}}
            <div class="absolute bottom-4 left-1/2 -translate-x-1/2">
                <button
                    type="button"
                    x-on:click="capturePhoto()"
                    class="flex items-center gap-2 px-5 py-2.5 bg-white/90 dark:bg-gray-800/90 backdrop-blur-sm
                           text-gray-900 dark:text-white rounded-full shadow-lg
                           hover:bg-white dark:hover:bg-gray-700 transition-all duration-200
                           ring-2 ring-primary-500/50"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary-500" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4 5a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2V7a2 2 0 00-2-2h-1.586a1 1 0 01-.707-.293l-1.121-1.121A2 2 0 0011.172 3H8.828a2 2 0 00-1.414.586L6.293 4.707A1 1 0 015.586 5H4zm6 9a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd" />
                    </svg>
                    <span class="font-medium text-sm">Capture Photo</span>
                </button>
            </div>
        </div>

        {{-- Hidden Canvas (used for capture, never displayed) --}}
        <canvas x-ref="canvas" class="hidden"></canvas>

        {{-- Captured Photo Preview --}}
        <div
            x-show="captured && photoData"
            x-transition
            class="relative"
            style="max-width: 480px;"
        >
            <img
                :src="photoData"
                alt="Captured employee photo"
                class="w-full rounded-xl border-2 border-primary-500/30 shadow-md"
            />

            {{-- Action Buttons --}}
            <div class="flex items-center gap-2 mt-3">
                <button
                    type="button"
                    x-on:click="retake()"
                    class="flex items-center gap-1.5 px-4 py-2 text-sm font-medium
                           bg-warning-50 dark:bg-warning-500/10 text-warning-700 dark:text-warning-400
                           rounded-lg border border-warning-200 dark:border-warning-500/30
                           hover:bg-warning-100 dark:hover:bg-warning-500/20 transition-colors"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd" />
                    </svg>
                    Retake
                </button>

                <button
                    type="button"
                    x-on:click="removePhoto()"
                    class="flex items-center gap-1.5 px-4 py-2 text-sm font-medium
                           bg-danger-50 dark:bg-danger-500/10 text-danger-700 dark:text-danger-400
                           rounded-lg border border-danger-200 dark:border-danger-500/30
                           hover:bg-danger-100 dark:hover:bg-danger-500/20 transition-colors"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                    Remove
                </button>
            </div>
        </div>

        {{-- Start Camera Button (shown when no stream and no photo) --}}
        <div x-show="!streaming && !captured">
            {{-- Show existing photo from storage if in edit mode and no base64 --}}
            @if($getRecord()?->employee_photo)
                <div class="mb-3" style="max-width: 480px;">
                    <img
                        src="{{ Storage::url($getRecord()->employee_photo) }}"
                        alt="Current employee photo"
                        class="w-full rounded-xl border-2 border-gray-200 dark:border-gray-700 shadow-sm"
                    />
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Current photo on file</p>
                </div>
            @endif

            <button
                type="button"
                x-on:click="startCamera()"
                class="flex items-center gap-2 px-5 py-3 text-sm font-medium
                       bg-primary-50 dark:bg-primary-500/10 text-primary-700 dark:text-primary-400
                       rounded-xl border-2 border-dashed border-primary-300 dark:border-primary-500/30
                       hover:bg-primary-100 dark:hover:bg-primary-500/20 hover:border-primary-400
                       transition-all duration-200 w-full justify-center"
                style="max-width: 480px;"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <span>{{ $getRecord()?->employee_photo ? 'Retake Photo via Webcam' : 'Open Webcam to Take Photo' }}</span>
            </button>
        </div>
    </div>
</x-dynamic-component>
