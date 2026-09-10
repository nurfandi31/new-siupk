<?php

declare(strict_types=1);

namespace App\Http\Controllers\Website;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Website\Models\SiteMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class WebsiteMessageController
{
    public function __construct(private readonly PermissionChecker $permissions) {}

    public function index(Request $request): Response
    {
        $this->permissions->denyUnless($request->user(), 'website.view');

        $search = trim((string) $request->query('q', ''));

        $messages = SiteMessage::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('subject', 'like', "%{$search}%")
                ->orWhere('message', 'like', "%{$search}%")))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (SiteMessage $message): array => [
                'row_id' => $message->row_id,
                'name' => $message->name,
                'email' => $message->email,
                'phone' => $message->phone,
                'subject' => $message->subject,
                'message' => $message->message,
                'is_read' => $message->isRead(),
                'created_at' => $message->created_at?->toIso8601String(),
            ]);

        $unreadCount = SiteMessage::query()->unread()->count();

        return Inertia::render('Website/Messages/Index', [
            'messages' => $messages,
            'search' => $search,
            'unreadCount' => $unreadCount,
        ]);
    }

    public function markRead(Request $request, SiteMessage $message): RedirectResponse
    {
        $this->permissions->denyUnless($request->user(), 'website.manage');

        if (! $message->isRead()) {
            $message->forceFill(['read_at' => now()])->save();
        }

        return back()->with('success', 'Pesan ditandai sudah dibaca.');
    }

    public function destroy(Request $request, SiteMessage $message): RedirectResponse
    {
        $this->permissions->denyUnless($request->user(), 'website.manage');

        $message->delete();

        return to_route('website.messages.index')->with('success', 'Pesan berhasil dihapus.');
    }
}
