<?php
/**
 * Display the results of a BLAST job execution
 */

// Set ourselves up to do link-out if our blast database is configured to do so.
$linkout = FALSE;
if ($blast_job->blastdb->linkout->none === FALSE) {
  $linkout_type  = $blast_job->blastdb->linkout->type;
  $linkout_regex = $blast_job->blastdb->linkout->regex;

  // Note that URL prefix is not required if linkout type is 'custom'
  if (isset($blast_job->blastdb->linkout->db_id->urlprefix) && !empty($blast_job->blastdb->linkout->db_id->urlprefix)) {
    $linkout_urlprefix = $blast_job->blastdb->linkout->db_id->urlprefix;
  }

  // Check that we can determine the linkout URL.
  // (ie: that the function specified to do so, exists).
  if (function_exists($blast_job->blastdb->linkout->url_function)) {
    $url_function = $blast_job->blastdb->linkout->url_function;
    $linkout = TRUE;
  }
}


// Handle no hits. This following array will hold the names of all query
// sequences which didn't have any hits.
$query_with_no_hits = array();

// Furthermore, if no query sequences have hits we don't want to bother listing
// them all but just want to give a single, all-include "No Results" message.
$no_hits = TRUE;

?>

<script type="text/javascript">

  // JQuery controlling display of the alignment information (hidden by default)
  $(document).ready(function(){

    // Hide the alignment rows in the table
    // (ie: all rows not labelled with the class "result-summary" which contains the tabular
    // summary of the hit)
    $("#blast_report tr:not(.result-summary)").hide();
    $("#blast_report tr:first-child").show();

    // When a results summary row is clicked then show the next row in the table
    // which should be corresponding the alignment information
    $("#blast_report tr.result-summary").click(function(){
      $(this).next("tr").toggle();
      $(this).find(".arrow").toggleClass("up");
    });
  });
</script>

<style>
.no-hits-message {
  color: red;
  font-style: italic;
}
</style>

<div class="blast-report">

    <!-- Provide Information to the user about their blast job -->
    <div class="blast-job-info">
      <div class="blast-download-info"><strong>Download</strong>:
        <a href="<?php print '../../' . $blast_job->files->result->html; ?>">Alignment</a>,
        <a href="<?php print '../../' . $blast_job->files->result->tsv; ?>">Tab-Delimited</a>,
        <a href="<?php print '../../' . $blast_job->files->result->xml; ?>">XML</a>,
        <a href="<?php print '../../' . $blast_job->files->result->gff; ?>">GFF3</a>
      </div>
      <br />
      <div class="blast-query-info"><strong>Query Information</strong>:
        <?php print $blast_job->files->query;?></div>
      <div class="blast-target-info"><strong>Search Target</strong>:
        <?php print $blast_job->blastdb->db_name;?></div>
      <div class="blast-date-info"><strong>Submission Date</strong>:
        <?php print format_date($blast_job->date_submitted, 'medium');?></div>
      <div class="blast-cmd-info"><strong>BLAST Command executed</strong>:
        <?php print $blast_job->blast_cmd;?></div>
      <br />
      <div class="num-results">
        <strong>Number of Results</strong>: <?php print $num_results; ?>
      </div>
    </div>

    <?php
      if ($show_cvit_diagram) {
    ?>
      <!-- CViTjs image of BLAST hits, if enabled -->
      <div class="cvitjs">
        <div id="title-div"><h2>Whole Genome Visualization of BLAST hits</h2></div>
        <div id="cvit-div"></div>
      </div>

    <?php
      }
    ?>

  <br />

  <div class="report-table">
    <?php
    /**
     * We are using the drupal table theme functionality to create this listing
     * @see theme_table() for additional documentation
     */

    if ($xml) {
    ?>

    <h2>Resulting BLAST hits</h2>

    <p>The following table summarizes the results of your BLAST.
    Click on a <em>triangle </em> on the left to see the alignment and a visualization of the hit,
    and click the <em>target name </em> to get more information about the target hit.</p>

    <?php
      // Specify the header of the table
      $header = array(
        'arrow-col' =>  array('data' => '', 'class' => array('arrow-col')),
        'number' =>  array('data' => '#', 'class' => array('number')),
        'query' =>  array('data' => 'Query Name  (Click for alignment & visualization)', 'class' => array('query')),
        'hit' =>  array('data' => 'Target Name', 'class' => array('hit')),
        'evalue' =>  array('data' => 'E-Value', 'class' => array('evalue')),
      );

      $rows = array();
      $count = 0;

      // Parse the BLAST XML to generate the rows of the table
      // where each hit results in two rows in the table: 1) A summary of the query/hit and
      // significance and 2) additional information including the alignment
      foreach ($xml->{'BlastOutput_iterations'}->children() as $iteration) {
        $children_count = $iteration->{'Iteration_hits'}->children()->count();

        // Save some information needed for the hit visualization.
        $target_name = '';
        $q_name = $xml->{'BlastOutput_query-def'};
        $query_size = $xml->{'BlastOutput_query-len'};
        $target_size = $iteration->{'Iteration_stat'}->{'Statistics'}->{'Statistics_db-len'};

        if ($children_count != 0) {
          foreach ($iteration->{'Iteration_hits'}->children() as $hit) {
            if (is_object($hit)) {
              $count +=1;
              $zebra_class = ($count % 2 == 0) ? 'even' : 'odd';
              $no_hits = FALSE;

              // SUMMARY ROW
              // -- Save additional information needed for the summary.
              $score = (float) $hit->{'Hit_hsps'}->{'Hsp'}->{'Hsp_score'};
              $evalue = (float) $hit->{'Hit_hsps'}->{'Hsp'}->{'Hsp_evalue'};
              $query_name = (string) $iteration->{'Iteration_query-def'};

              // If the id is of the form gnl|BL_ORD_ID|### then the parseids flag
              // to makeblastdb did a really poor job. In thhis case we want to use
              // the def to provide the original FASTA header.
              $hit_name = (preg_match('/BL_ORD_ID/', $hit->{'Hit_id'})) ? $hit->{'Hit_def'} : $hit->{'Hit_id'};

              // Used for the hit visualization to ensure the name isn't truncated.
              $hit_name_short = (preg_match('/^([^\s]+)/', $hit_name, $matches)) ? $matches[1] : $hit_name;

              // Round e-value to two decimal values.
              $rounded_evalue = '';
              if (strpos($evalue,'e') != false) {
                $evalue_split = explode('e', $evalue);
                $rounded_evalue = round($evalue_split[0], 2, PHP_ROUND_HALF_EVEN);
                $rounded_evalue .= 'e' . $evalue_split[1];
              }
              else {
                $rounded_evalue = $evalue;
              }

              // State what should be in the summary row for theme_table() later.
              $summary_row = array(
                'data' => array(
                  'arrow-col' => array('data' => '<div class="arrow"></div>', 'class' => array('arrow-col')),
                  'number' => array('data' => $count, 'class' => array('number')),
                  'query' => array('data' => $query_name, 'class' => array('query')),
                  'hit' => array('data' => $hit_name, 'class' => array('hit')),
                  'evalue' => array('data' => $rounded_evalue, 'class' => array('evalue')),
                ),
                'class' => array('result-summary')
              );

              // ALIGNMENT ROW (collapsed by default)
              // Process HSPs
              $HSPs = array();

              // We need to save some additional summary information in order to draw the
              // hit visualization. First, initialize some variables...
              $track_start = INF;
              $track_end = -1;
              $hsps_range = '';
              $hit_hsps = '';
              $hit_hsp_score = '';
              $target_size = $hit->{'Hit_len'};
              $Hsp_bit_score = '';

              // Then for each hit hsp, keep track of the start of first hsp and the end of
              // the last hsp. Keep in mind that hsps might not be recorded in order.
              foreach ($hit->{'Hit_hsps'}->children() as $hsp_xml) {
                $HSPs[] = (array) $hsp_xml;

                if ($track_start > $hsp_xml->{'Hsp_hit-from'}) {
                  $track_start = $hsp_xml->{'Hsp_hit-from'} . "";
                }
                if ($track_end < $hsp_xml->{'Hsp_hit-to'}) {
                  $track_end = $hsp_xml->{'Hsp_hit-to'} . "";
                }

                // The BLAST visualization code requires the hsps to be formatted in a
                // very specific manner. Here we build up the strings to be submitted.
                // hits=4263001_4262263_1_742;4260037_4259524_895_1411;&scores=722;473;
                $hit_hsps .=  $hsp_xml->{'Hsp_hit-from'} . '_' .
                              $hsp_xml->{'Hsp_hit-to'} . '_' .
                              $hsp_xml->{'Hsp_query-from'} . '_' . $hsp_xml->{'Hsp_query-to'} .
                              ';';
                $Hsp_bit_score .=   $hsp_xml->{'Hsp_bit-score'} .';';
              }

              // Finally record the range.
              // @todo figure out why we arbitrarily subtract 50,000 here...
              // @more removing the 50,000 and using track start/end appears to cause no change...
              $range_start = (int) $track_start;// - 50000;
              $range_end = (int) $track_end;// + 50000;
              if ($range_start < 1) $range_start = 1;

              // Call the function to generate the hit image.
              $hit_img = generate_blast_hit_image($target_name, $Hsp_bit_score, $hit_hsps,
                                       $target_size, $query_size, $q_name, $hit_name_short);


              // get blast program
              $blast_cmd_arr = explode(' ',trim($blast_job->blast_cmd));
              $blast_cmd_program =  $blast_cmd_arr[0];


              // State what should be in the alignment row for theme_table() later.
              $alignment_row = array(
                'data' => array(
                  'arrow' => array(
                    'data' => theme('blast_report_alignment_row', array('HSPs' => $HSPs, 'hit_visualization' => $hit_img, 'blast_program' => $blast_cmd_program)),
                    'colspan' => 5,
                  ),
                ),
                'class' => array('alignment-row', $zebra_class),
                'no_striping' => TRUE
              );

              // LINK-OUTS.
              // It was determined above whether link-outs were supported for the
              // tripal blast database used as a search target. Thus we only want to
              // determine a link-out if it's actually supported... ;-)
              if ($linkout) {

                // First extract the linkout text using the regex provided through
                // the Tripal blast database node.
                if (preg_match($linkout_regex, $hit_name, $linkout_match)) {

                  $hit->{'linkout_id'} = $linkout_match[1];
                  $hit->{'hit_name'} = $hit_name;

                  // Allow custom functions to determine the URL to support more complicated
                  // link-outs rather than just using the tripal database prefix.
                  $hit_name = call_user_func(
                    $url_function,
                    $linkout_urlprefix,
                    $hit,
                    array(
                      'query_name' => $query_name,
                      'score'      => $score,
                      'e-value'    => $evalue,
                      'HSPs'       => $HSPs,
                      'Target'     => $blast_job->blastdb->db_name,
                      'RegEx'      => $linkout_regex,
                    )
                  );
                }

                // Replace the target name with the link.
                $summary_row['data']['hit']['data'] = $hit_name;
              }

              // ADD TO TABLE ROWS
              $rows[] = $summary_row;
              $rows[] = $alignment_row;

            }//end of if - checks $hit
          }//end of foreach - iteration_hits
        }//end of if - check for iteration_hits

        else {
          // Currently where the "no results" is added.
          $query_name = $iteration->{'Iteration_query-def'};
          $query_with_no_hits[] = $query_name;

        }//no results
      }//end of foreach - BlastOutput_iterations

      if ($no_hits) {
        print '<p class="no-hits-message">No results found.</p>';
      }
      else {
        // We want to warn the user if some of their query sequences had no hits.
        if (!empty($query_with_no_hits)) {
          print '<p class="no-hits-message">Some of your query sequences did not '
          . 'match to the database/template. They are: '
          . implode(', ', $query_with_no_hits) . '.</p>';
        }

        // Actually print the table.
        if (!empty($rows)) {
          print theme('table', array(
            'header' => $header,
            'rows' => $rows,
            'attributes' => array('id' => 'blast_report'),
            'sticky' => FALSE
          ));
        }
      }//handle no hits
    }//XML exists
    elseif ($too_many_results) {
      print '<div class="messages error">Your BLAST resulted in '. number_format(floatval($num_results)) .' results which is too many to reasonably display. We have provided the result files for Download at the top of this page; however, we suggest you re-submit your query using a more stringent e-value (i.e. a smaller number).</div>';
    }
    else {
      drupal_set_title('BLAST: Error Encountered');
      print '<div class="messages error">We encountered an error and are unable to load your BLAST results.</div>';
    }
  ?>

  <p><?php print l(
    'Edit this query and re-submit',
    $blast_form_url,
    array('query' => array('resubmit' => blast_ui_make_secret($job_id))));
  ?></p>
</div>

<?php print theme('blast_recent_jobs', array()); ?>
</div>
