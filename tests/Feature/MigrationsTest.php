<?php

use Illuminate\Support\Facades\Schema;

it('creates all mahalinkam tables with key columns', function () {
    foreach (['folders', 'bookmarks', 'tags', 'bookmark_tag', 'imports'] as $t) {
        expect(Schema::hasTable($t))->toBeTrue("missing table {$t}");
    }
    expect(Schema::hasColumns('bookmarks', ['user_id', 'folder_id', 'url', 'normalized_url', 'title', 'description', 'favicon_url', 'metadata_status']))->toBeTrue();
    expect(Schema::hasColumns('folders', ['user_id', 'parent_id', 'name', 'position']))->toBeTrue();
    expect(Schema::hasColumns('tags', ['user_id', 'name', 'name_lower']))->toBeTrue();
    expect(Schema::hasColumns('imports', ['user_id', 'format', 'status', 'total_rows', 'processed_rows', 'created_count', 'duplicate_count', 'error_count', 'errors', 'original_filename']))->toBeTrue();
});
