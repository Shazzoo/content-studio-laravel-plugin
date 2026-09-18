<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_studio_articles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('content_studio_article_id')->nullable();
            $table->string('locale', 10)->index();
            $table->string('title')->nullable();
            $table->text('excerpt')->nullable();
            $table->longText('body_html')->nullable();
            $table->string('featured_image_url')->nullable();
            $table->string('featured_image_alt')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('seo_title')->nullable();
            $table->string('og_title')->nullable();
            $table->text('og_description')->nullable();
            $table->string('twitter_title')->nullable();
            $table->text('twitter_description')->nullable();
            $table->string('slug')->nullable()->index();
            $table->string('published_url')->nullable();
            $table->timestamp('published_confirmed_at')->nullable();
            $table->json('cta')->nullable();
            $table->string('primary_keyword')->nullable();
            $table->json('cluster')->nullable();
            $table->string('funnel_stage')->nullable();
            $table->string('intent')->nullable();
            $table->string('angle')->nullable();
            $table->string('source_month')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('planned_at')->nullable();
            $table->string('content_type')->nullable();
            $table->string('cluster_key')->nullable();
            $table->string('hub_content_id')->nullable();
            $table->string('author_name')->nullable();
            $table->string('author_role_title')->nullable();
            $table->string('author_experience_label')->nullable();
            $table->text('author_experience_summary')->nullable();
            $table->text('author_article_relevance')->nullable();
            $table->text('author_boundary_note')->nullable();
            $table->timestamps();

            $table->unique(['content_studio_article_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_studio_articles');
    }
};
