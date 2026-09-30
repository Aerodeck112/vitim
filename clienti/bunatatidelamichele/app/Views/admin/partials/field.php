<?php
/** @var array $f câmpul  @var mixed $val valoarea curentă */
$n = $f['name'];
$id = 'fld_' . $n;
$req = !empty($f['required']) ? ' required' : '';
?>
<div class="fl">
<?php if ($f['type'] === 'checkbox'): ?>
  <label class="chk" style="font-weight:600"><input type="checkbox" name="<?= e($n) ?>" value="1"<?= $val ? ' checked' : '' ?>> <?= e($f['label']) ?></label>
<?php else: ?>
  <label for="<?= e($id) ?>"><?= e($f['label']) ?><?= $req ? ' <span style="color:var(--err)">*</span>' : '' ?></label>
  <?php switch ($f['type']):
    case 'textarea': ?>
    <textarea class="in" id="<?= e($id) ?>" name="<?= e($n) ?>" rows="3"<?= $req ?>><?= e($val) ?></textarea>
    <?php break; case 'richtext': ?>
    <textarea id="<?= e($id) ?>" name="<?= e($n) ?>" data-rte><?= e($val) ?></textarea>
    <?php break; case 'select': ?>
    <select class="in" id="<?= e($id) ?>" name="<?= e($n) ?>">
      <?php foreach ($f['options'] as $k => $label): ?><option value="<?= e($k) ?>"<?= (string)$val === (string)$k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
    </select>
    <?php break; case 'number': ?>
    <input class="in" type="number" id="<?= e($id) ?>" name="<?= e($n) ?>" value="<?= e($val ?? 0) ?>">
    <?php break; case 'datetime': ?>
    <input class="in" type="datetime-local" id="<?= e($id) ?>" name="<?= e($n) ?>" value="<?= $val ? e(local_time((string)$val, 'Y-m-d\TH:i')) : '' ?>">
    <?php break; case 'slug': ?>
    <div style="display:flex;align-items:center;gap:6px"><span class="muted small mono"><?= e($f['prefix'] ?? '/') ?></span><input class="in mono" id="<?= e($id) ?>" name="<?= e($n) ?>" value="<?= e($val) ?>" data-slug-from="<?= e($f['from']) ?>" pattern="[a-z0-9-]*"></div>
    <?php break; case 'image': ?>
    <div class="img-field" data-image-field>
      <input type="hidden" name="<?= e($n) ?>" value="<?= e($val) ?>">
      <div class="prev" style="<?= $val ? 'background-image:url(' . e(upload_url((string)$val)) . ')' : '' ?>"></div>
      <div class="actions"><button type="button" class="btn btn-sm" data-pick>Alege</button><button type="button" class="btn btn-sm btn-ghost" data-clear>Elimină</button></div>
    </div>
    <?php break; case 'repeater': ?>
    <div data-repeater data-fields="<?= e(json_encode($f['fields'], JSON_UNESCAPED_UNICODE)) ?>">
      <input type="hidden" name="<?= e($n) ?>" value="<?= e(is_string($val) ? $val : json_encode($val ?: [], JSON_UNESCAPED_UNICODE)) ?>">
      <div class="rep-list"></div>
      <button type="button" class="btn btn-sm" data-rep-add style="margin-top:10px"><?= icon('plus') ?> Adaugă</button>
    </div>
    <?php break; default: ?>
    <input class="in" type="text" id="<?= e($id) ?>" name="<?= e($n) ?>" value="<?= e($val) ?>"<?= $req ?>>
  <?php endswitch; ?>
<?php endif; ?>
<?php if (!empty($f['hint'])): ?><div class="hint"><?= e($f['hint']) ?></div><?php endif; ?>
</div>
