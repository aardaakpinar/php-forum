<?php
/**
 * Markdown editör (araç çubuğu + textarea).
 * @var string $editorId, $editorName, $editorValue, $editorRows
 */
$editorRows = $editorRows ?? 6;
$editorMax = (int)site_setting('max_post_length');
?>
<div class="editor" data-editor>
    <div class="editor-toolbar" role="toolbar" aria-label="Formatting">
        <button type="button" data-md="bold" title="Bold" aria-label="Bold"><i data-lucide="bold"></i></button>
        <button type="button" data-md="italic" title="Italic" aria-label="Italic"><i data-lucide="italic"></i></button>
        <button type="button" data-md="strike" title="Strikethrough" aria-label="Strikethrough"><i data-lucide="strikethrough"></i></button>
        <span class="tb-sep"></span>
        <button type="button" data-md="quote" title="Quote" aria-label="Quote"><i data-lucide="quote"></i></button>
        <button type="button" data-md="code" title="Code" aria-label="Code"><i data-lucide="code"></i></button>
        <button type="button" data-md="link" title="Link" aria-label="Link"><i data-lucide="link"></i></button>
        <span class="tb-sep"></span>
        <button type="button" data-md="ul" title="Bulleted list" aria-label="Bulleted list"><i data-lucide="list"></i></button>
        <button type="button" data-md="ol" title="Numbered list" aria-label="Numbered list"><i data-lucide="list-ordered"></i></button>
        <span class="char-count" data-count data-max="<?= $editorMax ?>">0 / <?= $editorMax ?></span>
    </div>
    <textarea id="<?= e($editorId) ?>" name="<?= e($editorName) ?>" rows="<?= (int)$editorRows ?>" maxlength="<?= $editorMax ?>" required placeholder="Write something… Markdown is supported."><?= e($editorValue ?? '') ?></textarea>
</div>
<small class="field-help">Markdown supported. Use <code>@username</code> to mention someone and <code>#123</code> to link a thread.</small>
