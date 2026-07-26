<?php

use App\Mcp\Servers\ScoreAnalysisServer;
use Laravel\Mcp\Facades\Mcp;

// Score Analysis API — provides score distribution and gap analysis tools.
// Admin-only: this returns org-wide, unscoped score data (ScoreDistributionService's
// own scoping only narrows for a resolved non-admin Auth::user(); an unauthenticated
// caller would otherwise fall straight into the "show everything" branch).
Mcp::web('/mcp/score-analysis', ScoreAnalysisServer::class)->middleware('admin');
