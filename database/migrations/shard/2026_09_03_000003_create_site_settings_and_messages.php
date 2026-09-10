<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function schema(): Builder
    {
        return Schema::connection((string) config('tenancy.tenant_connection', 'tenant'));
    }

    public function up(): void
    {
        // One settings row per tenant. Created lazily by the settings
        // controller on first save, so no sequence/id plumbing is needed.
        $this->schema()->create('site_settings', function (Blueprint $table): void {
            $table->bigIncrements('row_id');
            $table->unsignedBigInteger('tenant_id');
            $table->string('hero_tagline', 200)->nullable();
            $table->string('hero_description', 500)->nullable();
            $table->string('hero_image_path', 500)->nullable();
            $table->string('about_short', 500)->nullable();
            $table->string('facebook_url', 255)->nullable();
            $table->string('instagram_url', 255)->nullable();
            $table->string('youtube_url', 255)->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->string('contact_email', 255)->nullable();
            $table->string('contact_address', 500)->nullable();
            $table->string('footer_note', 255)->nullable();
            $table->timestamps();

            $table->unique('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenant_registry')->cascadeOnDelete();
        });

        // Public contact-form submissions. Not sequenced: messages are
        // private to the tenant inbox and never need tenant-local ids.
        $this->schema()->create('site_messages', function (Blueprint $table): void {
            $table->bigIncrements('row_id');
            $table->unsignedBigInteger('tenant_id');
            $table->string('name', 120);
            $table->string('email', 255)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('subject', 200)->nullable();
            $table->text('message');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'read_at']);
            $table->foreign('tenant_id')->references('id')->on('tenant_registry')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('site_messages');
        $this->schema()->dropIfExists('site_settings');
    }
};
