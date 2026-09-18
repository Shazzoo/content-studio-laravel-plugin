<?php

namespace Shazzoo\ContentStudio\Console;

use Illuminate\Console\Command;
use Shazzoo\ContentStudio\Engine\SyncArticles;

class SyncArticlesCommand extends Command
{
    protected $signature = 'content-studio:sync {--status=approved : Status filter for the Engine: approved, published, a comma list, or "all"}';

    protected $description = 'Sync the blog articles from the Content Studio Engine.';

    public function handle(SyncArticles $sync): int
    {
        $result = $sync((string) $this->option('status'));

        $this->line('Synced articles: '.$result['synced']);
        $this->line('Skipped (not approved): '.$result['skipped']);
        if ($result['expected'] !== null) {
            $this->line('Available on Engine: '.$result['expected']);
        }
        $this->line('Pages processed: '.$result['pages']);

        if (! $result['ok']) {
            $this->error($result['message']);

            return self::FAILURE;
        }

        $this->info($result['message']);

        return self::SUCCESS;
    }
}
