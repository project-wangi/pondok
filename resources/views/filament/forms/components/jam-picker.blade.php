<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div
        x-data="{
            state: $wire.$entangle('{{ $getStatePath() }}', true),
            disabled: @js($isDisabled()),
            open: false,
            flipped: false,
            get value() {
                return (this.state ?? '').slice(0, 5)
            },
            get hour() {
                return this.value.slice(0, 2) || '00'
            },
            get minute() {
                return this.value.slice(3, 5) || '00'
            },
            toggle(focusOptions = false) {
                if (this.disabled) {
                    return
                }

                this.open = ! this.open

                if (! this.open) {
                    this.$refs.input.focus()

                    return
                }

                this.reveal(focusOptions)
            },
            reveal(focusOptions) {
                this.$nextTick(() => {
                    const rect = this.$el.getBoundingClientRect()
                    const panelHeight = this.$refs.panel.offsetHeight

                    this.flipped = (rect.bottom + panelHeight + 8 > window.innerHeight)
                        && (rect.top > panelHeight + 8)

                    this.$el.querySelectorAll('.jam-picker-column').forEach((column) => {
                        const selected = column.querySelector('[aria-selected=\'true\']')

                        if (selected) {
                            column.scrollTop = selected.offsetTop - (column.clientHeight / 2) + (selected.clientHeight / 2)
                        }
                    })

                    if (! focusOptions) {
                        return
                    }

                    const target = this.$refs.panel.querySelector('.jam-picker-column [aria-selected=\'true\']')
                        ?? this.$refs.panel.querySelector('.jam-picker-column .jam-picker-option')

                    target?.focus()
                })
            },
            pickHour(hour) {
                this.state = hour + ':' + this.minute
            },
            pickMinute(minute) {
                this.state = this.hour + ':' + minute
                this.close()
            },
            close() {
                this.open = false
                this.$refs.input.focus()
            },
            clear() {
                this.state = null
                this.close()
            },
            step(column, direction) {
                const options = [ ...column.querySelectorAll('.jam-picker-option') ]
                const index = options.indexOf(document.activeElement)
                const target = options[ Math.min(options.length - 1, Math.max(0, index + direction)) ]

                target?.focus()
            },
        }"
        x-on:keydown.escape.window="open && close()"
        x-on:click.outside="open = false"
        class="jam-picker"
    >
        <x-filament::input.wrapper :valid="! $errors->has($getStatePath())">
            <x-filament::input
                x-ref="input"
                id="{{ $getId() }}"
                type="text"
                readonly
                x-bind:value="state ? value : ''"
                x-bind:aria-expanded="open"
                x-on:click="toggle()"
                x-on:keydown.enter.prevent="toggle(true)"
                x-on:keydown.space.prevent="toggle(true)"
                x-on:keydown.down.prevent="toggle(true)"
                aria-haspopup="listbox"
                placeholder="Pilih jam"
                class="jam-picker-input"
            />
        </x-filament::input.wrapper>

        <div
            x-cloak
            x-ref="panel"
            x-show="open"
            x-bind:class="{ 'is-flipped': flipped }"
            x-transition.opacity.duration.150ms
            class="jam-picker-panel"
        >
            <div class="jam-picker-columns">
                <div
                    class="jam-picker-column"
                    role="listbox"
                    aria-label="Jam"
                    x-on:keydown.up.prevent="step($el, -1)"
                    x-on:keydown.down.prevent="step($el, 1)"
                >
                    <span class="jam-picker-column-title">Jam</span>

                    @foreach ($getHours() as $hourLabel)
                        <button
                            type="button"
                            role="option"
                            class="jam-picker-option"
                            x-bind:class="{ 'is-selected': hour === '{{ $hourLabel }}' }"
                            x-bind:aria-selected="hour === '{{ $hourLabel }}'"
                            x-on:click="pickHour('{{ $hourLabel }}')"
                        >
                            {{ $hourLabel }}
                        </button>
                    @endforeach
                </div>

                <div
                    class="jam-picker-column"
                    role="listbox"
                    aria-label="Menit"
                    x-on:keydown.up.prevent="step($el, -1)"
                    x-on:keydown.down.prevent="step($el, 1)"
                >
                    <span class="jam-picker-column-title">Menit</span>

                    @foreach ($getMinutes() as $minuteLabel)
                        <button
                            type="button"
                            role="option"
                            class="jam-picker-option"
                            x-bind:class="{ 'is-selected': minute === '{{ $minuteLabel }}' }"
                            x-bind:aria-selected="minute === '{{ $minuteLabel }}'"
                            x-on:click="pickMinute('{{ $minuteLabel }}')"
                        >
                            {{ $minuteLabel }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="jam-picker-footer">
                <button type="button" class="jam-picker-clear" x-on:click="clear()">
                    Kosongkan
                </button>
                <button type="button" class="jam-picker-done" x-on:click="close()">
                    Selesai
                </button>
            </div>
        </div>
    </div>
</x-dynamic-component>
