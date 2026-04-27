<?php

use App\Mcp\Servers\ScoreAnalysisServer;
use Laravel\Mcp\Facades\Mcp;

// Score Analysis API — provides score distribution and gap analysis tools
Mcp::web('/mcp/score-analysis', ScoreAnalysisServer::class);
