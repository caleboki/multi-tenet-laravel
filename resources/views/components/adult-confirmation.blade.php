<div class="flex flex-col gap-1">
    <label class="flex items-start gap-2 text-sm">
        <input
            type="checkbox"
            name="adult_confirmation"
            value="1"
            @checked(old('adult_confirmation'))
            @error('adult_confirmation') aria-invalid="true" aria-describedby="adult_confirmation-error" @enderror
            class="mt-0.5 size-4 rounded border-zinc-300 focus-visible:outline-2 focus-visible:outline-indigo-600 dark:border-zinc-700"
        >
        I confirm I am 18 or older.
    </label>

    @error('adult_confirmation')
        <p id="adult_confirmation-error" class="text-sm text-red-700 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>
