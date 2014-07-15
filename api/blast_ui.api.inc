<?php

/**
 * @file
 * Contains more generally applicable functions as well as some meant to help developers
 * Plug-in to the BLAST UI functionality
 */

/**
 * Returns a list BLAST DATABASE options
 *
 * @param $type
 *   The type of BLAST dabases to restrict the list to (ie: n: nucleotide or p: protein)
 *
 * @return
 *   An array where the nid is the key and the value is the human-readable name of the option
 */
function get_blast_database_options($type) {

  // Get all BlastDB nodes
  $nodes  = node_load_multiple(array(), array('type'=> 'blastdb'));

  $options = array();
  foreach ($nodes as $node) {
    if ( isset($node) && isset($node->db_dbtype) ) {
	    if ( ($node->db_dbtype == $type) ) {
        $options[$node->nid] = $node->db_name;
	    }
    }
  }

  asort($options);
  $options[0] = 'Select a Dataset';

  return $options;
}

/**
 * Run BLAST (should be called from the command-line)
 *
 * @param $program
 *   Which BLAST program to run (ie: 'blastn', 'tblastn', tblastx', 'blastp','blastx')
 * @param $query
 *   The full path and filename of the query FASTA file
 * @param $database
 *   The full path and filename prefix (excluding .nhr, .nin, .nsq, etc.)
 * @param $output_filename
 *   The filename (not including path) to give the results
 * @param $options
 *   An array of additional option where the key is the name of the option used by
 *   BLAST (ie: 'num_alignments') and the value is relates to this particular
 *   BLAST job (ie: 250)
 */
function run_BLAST_tripal_job($program, $query, $database, $output_file, $options, $job_id = NULL) {

  $output_file = 'sites/default/files/' . $output_file;

  print "\nExecuting $program\n\n";
  print "Query: $query\n";
  print "Database: $database\n";
  print "Results File: $output_file\n";

  print "Options:\n";

  $blast_cmd = "$program -query $query -db $database -out $output_file -outfmt=5";
  if (!empty($options)) {
    foreach ($options as $opt => $val) {
      print "\t$opt: $val\n";
      $blast_cmd .= " -$opt $val";
    }
  }

  print "\nExecuting the following BLAST command:\n" . $blast_cmd . "\n";

  system($blast_cmd);

  print "\nDone!\n";
}