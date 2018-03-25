<?php
// Warning: the mapping is not one-to-one, so some data may be lost when the
// mapping is reverted. You may adapt it to your needs.

// https://www.zotero.org/support/kb/item_types_and_fields#item_creators
return [
    'bibo:editor'           => 'editor',
    'bibo:director'         => 'director',
    'bibo:interviewee'      => 'interviewee',
    'bibo:interviewer'      => 'interviewer',
    'bibo:performer'        => 'performer',
    'bibo:producer'         => 'producer',
    'bibo:recipient'        => 'recipient',
    'bibo:translator'       => 'translator',
    'dcterms:contributor'   => 'contributor',
    'dcterms:creator'       => 'author',

    /*
    // Not managed (or managed in other properties).
    'bibo:authorList'       => '',
    'bibo:contributorList'  => '',
    'bibo:distributor'      => '', // property
    'bibo:editorList'       => '',
    'bibo:organizer'        => '', // property
    'bibo:owner'            => '',
    'dcterms:mediator'      => '',
    'dcterms:publisher'     => '', // property
    'dcterms:rightsHolder'  => '',
    */
];
