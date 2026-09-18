<?php

namespace Shazzoo\ContentStudio\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Shazzoo\ContentStudio\Models\Article;

class ClearArticlesCommand extends Command
{
    /** Where the sync stores the images it downloads. */
    private const IMAGE_DIRECTORY = 'content_studio_images';

    protected $signature = 'content-studio:clear
        {--images : Also delete the downloaded images}
        {--force : Delete without asking for confirmation}';

    protected $description = 'Delete every synced article from the database.';

    public function handle(): int
    {
        $count = Article::query()->count();
        $images = (bool) $this->option('images');

        if ($count === 0 && ! $images) {
            $this->info('There are no articles to delete.');

            return self::SUCCESS;
        }

        $what = $images ? "{$count} articles and their images" : "{$count} articles";

        if (! $this->option('force') && ! $this->confirm("Delete {$what}?")) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        Article::query()->delete();

        if ($images) {
            Storage::disk('public')->deleteDirectory(self::IMAGE_DIRECTORY);
            $this->info("Deleted {$count} articles and their images. A new sync downloads the images again.");

            return self::SUCCESS;
        }

        $this->info("Deleted {$count} articles. The images stay in storage; a new sync reuses them.");

        return self::SUCCESS;
    }
}
