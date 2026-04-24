<?php

return [
    'url'      => env('COUCHDB_URL', 'http://localhost:5984'),
    'user'     => env('COUCHDB_USER', ''),
    'password' => env('COUCHDB_PASSWORD', ''),

    // CouchDB database names — must match what the monitoring app writes to
    'databases' => [
        'sessions'  => env('COUCHDB_DB_SESSIONS',  'penplus_sessions'),
        'gaps'      => env('COUCHDB_DB_GAPS',       'penplus_gaps'),
        'users'     => env('COUCHDB_DB_USERS',      'penplus_users'),
        'districts' => env('COUCHDB_DB_DISTRICTS',  'penplus_districts'),
        'facilities'=> env('COUCHDB_DB_FACILITIES', 'penplus_facilities'),
    ],
];
