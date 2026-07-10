<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Lead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function store(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorize('update', $lead);

        $maxKb = (int) (config('crm.attachments.max_size') / 1024);
        $mimes = implode(',', config('crm.attachments.allowed_mimes'));

        $request->validate([
            'file' => ['required', 'file', "max:{$maxKb}", "mimetypes:{$mimes}"],
        ]);

        $disk = config('crm.attachments.disk');
        $file = $request->file('file');
        $path = $file->store('attachments/'.$lead->id, $disk);

        $lead->attachments()->create([
            'disk' => $disk,
            'path' => $path,
            'filename' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime' => $file->getMimeType(),
            'uploaded_by' => $request->user()->id,
        ]);

        return back()->with('status', 'attachment-uploaded');
    }

    public function download(Request $request, Attachment $attachment): StreamedResponse
    {
        $attachable = $attachment->attachable;

        if ($attachable instanceof Lead) {
            $this->authorize('view', $attachable);
        }

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->filename);
    }

    public function destroy(Request $request, Attachment $attachment): RedirectResponse
    {
        $user = $request->user();
        abort_unless($attachment->uploaded_by === $user->id || $user->isAdmin(), 403);

        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();

        return back()->with('status', 'attachment-deleted');
    }
}
