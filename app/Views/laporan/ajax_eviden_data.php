<div class="table-responsive" style="max-height: 300px; overflow-y: auto; border: 1px solid #3d3d3d; border-radius: 4px;">
    <table class="table table-bordered table-striped table-hover text-center table-modern mb-0" style="font-size: 0.85rem;">
        <thead class="bg-dark" style="position: sticky; top: 0; z-index: 100;">
            <tr>
                <th width="8%">
                    <input type="checkbox" id="checkAll">
                </th>
                <th>Nama Gardu</th>
                <th>Section</th>
                <th>Tanggal Input</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($dataList)): ?>
                <tr>
                    <td colspan="4" class="text-muted py-3">Tidak ada data eviden yang ditemukan untuk filter ini.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($dataList as $row): ?>
                    <tr>
                        <td>
                            <input type="checkbox" name="selected_ids[]" class="checkItem" value="<?= $row['id'] ?>">
                        </td>
                        <td>
                            <span class="badge bg-secondary px-2 py-1"><?= esc($row['nama_gardu']) ?></span>
                        </td>
                        <td><?= esc($row['nama_section'] ?: '-') ?></td>
                        <td><?= date('d-m-Y', strtotime($row['tgl_input'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
    $(function() {
        // Toggle check all checkboxes
        $('#checkAll').change(function() {
            $('.checkItem').prop('checked', $(this).prop('checked'));
        });

        // Toggle checkAll state based on items selection
        $('.checkItem').change(function() {
            if ($('.checkItem:checked').length === $('.checkItem').length) {
                $('#checkAll').prop('checked', true);
            } else {
                $('#checkAll').prop('checked', false);
            }
        });
    });
</script>
