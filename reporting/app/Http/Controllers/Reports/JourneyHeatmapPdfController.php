<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\ReportQueryService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Spatie\Browsershot\Browsershot;

class JourneyHeatmapPdfController extends Controller
{
    public function __construct(
        private readonly ReportQueryService $queries,
    ) {}

    public function __invoke(Request $request): Response
    {
        $groupId = $request->input('group_id');

        if (! $groupId) {
            abort(404);
        }

        $journey = $this->queries->getJourneySummaryData($groupId);

        if (! $journey->getGroupId()) {
            abort(404);
        }

        // Renders the same authenticated Inertia page a browser would show,
        // in real headless Chrome, so the PDF matches on-screen Tailwind
        // styling exactly (including the print-only CSS the page injects
        // itself — landscape @page size, exact color printing). Forwarding
        // the current request's cookies lets headless Chrome reuse this
        // user's session instead of hitting the login redirect.
        //
        // $request->cookies (and Request::cookie()) hold cookies *after*
        // EncryptCookies has decrypted them — reusing those values makes
        // Chrome send plaintext where Laravel expects ciphertext, so the
        // decrypt silently fails and the session looks unauthenticated.
        // $_COOKIE still has the raw, still-encrypted values a browser
        // actually sent, which is what needs to be forwarded verbatim.
        $targetUrl = $request->root().'/journey-heatmap?'.http_build_query(['group_id' => $groupId]);

        $pdf = Browsershot::url($targetUrl)
            ->useCookies($_COOKIE)
            ->windowSize(1600, 1000)
            ->waitUntilNetworkIdle()
            ->emulateMedia('print')
            ->showBackground()
            ->landscape()
            ->margins(10, 10, 10, 10)
            ->noSandbox()
            ->timeout(60)
            ->pdf();

        $filename = Str::slug($journey->getMenteeName().'-'.$journey->getToolLabel().'-heatmap').'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
