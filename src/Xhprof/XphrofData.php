<?php

namespace Skylee\MonitorClient\Xhprof;

class XphrofData
{
    protected $sortable_columns
        = [
            "fn"           => 1,
            "ct"           => 1,
            "wt"           => 1,
            "excl_wt"      => 1,
            "ut"           => 1,
            "excl_ut"      => 1,
            "st"           => 1,
            "excl_st"      => 1,
            "mu"           => 1,
            "excl_mu"      => 1,
            "pmu"          => 1,
            "excl_pmu"     => 1,
            "cpu"          => 1,
            "excl_cpu"     => 1,
            "samples"      => 1,
            "excl_samples" => 1,
        ];
    protected $pc_stats;

    protected $stats = [];
    protected $metrics = [];
    protected $sort_col = "wt";
    protected $display_calls = true;


    protected function xhprof_parse_parent_child($parent_child)
    {
        $ret = explode("==>", $parent_child);

        // Return if both parent and child are set
        if (isset($ret[1])) {
            return $ret;
        }

        return array(null, $ret[0]);
    }


    /*
 * 作为 XHProf 的一部分收集的可能指标列表，需要在报告时进行包容性独家处理。     *
 * @author Kannan
 */
    protected function xhprof_get_possible_metrics()
    {
        static $possible_metrics
            = array(
            "wt"      => array("Wall", "microsecs", "walltime"),
            "ut"      => array("User", "microsecs", "user cpu time"),
            "st"      => array("Sys", "microsecs", "system cpu time"),
            "cpu"     => array("Cpu", "microsecs", "cpu time"),
            "mu"      => array("MUse", "bytes", "memory usage"),
            "pmu"     => array("PMUse", "bytes", "peak memory usage"),
            "samples" => array("Samples", "samples", "cpu time"),
        );

        return $possible_metrics;
    }


    /*
 * Get the list of metrics present in $xhprof_data as an array.
 *
 * @author Kannan
 */
    protected function xhprof_get_metrics($xhprof_data)
    {
        // get list of valid metrics
        $possible_metrics = $this->xhprof_get_possible_metrics();

        // return those that are present in the raw data.
        // We'll just look at the root of the subtree for this.
        $metrics = array();
        foreach ($possible_metrics as $metric => $desc) {
            if (isset($xhprof_data["main()"][$metric])) {
                $metrics[] = $metric;
            }
        }

        return $metrics;
    }

    /**
     *
     * 返回 XHProf 原始数据的修剪版本。请注意，
     * 原始数据包含每个唯一父子函数组合的条目。
     * 原始数据的修剪版本将仅包含父函数或子函数在 functions_to_keep 列表中的条目。
     *
     * @param  array  XHProf raw data
     * @param  array  array of function names
     *
     * @return array  Trimmed XHProf Report
     *
     * @author Kannan
     */
    function xhprof_trim_run($raw_data, $functions_to_keep)
    {
        // convert list of functions to a hash with function as the key
        $function_map = array_fill_keys($functions_to_keep, 1);

        // always keep main() as well so that overall totals can still
        // be computed if need be.
        $function_map['main()'] = 1;

        $new_raw_data = array();
        foreach ($raw_data as $parent_child => $info) {
            list($parent, $child) = $this->xhprof_parse_parent_child($parent_child);

            if (isset($function_map[$parent]) || isset($function_map[$child])) {
                $new_raw_data[$parent_child] = $info;
            }
        }

        return $new_raw_data;
    }

    public function profiler_report(
        $url_params,
        $rep_symbol,
        $sort,
        $run1,
        $run1_desc,
        $run1_data,
        $run2 = 0,
        $run2_desc = "",
        $run2_data = array()
    ) {
        global $totals;
        global $totals_1;
        global $totals_2;


        // if we are reporting on a specific function, we can trim down
        // the report(s) to just stuff that is relevant to this function.
        // That way compute_flat_info()/compute_diff() etc. do not have
        // to needlessly work hard on churning irrelevant data.
        if ( ! empty($rep_symbol)) {
            $run1_data = $this->xhprof_trim_run($run1_data, array($rep_symbol));
            if ($diff_mode) {
                $run2_data = $this->xhprof_trim_run($run2_data, array($rep_symbol));
            }
        }

        if ($diff_mode) {
//            $run_delta   = xhprof_compute_diff($run1_data, $run2_data);
            $symbol_tab  = $this->xhprof_compute_flat_info($run_delta, $totals);
            $symbol_tab1 = $this->xhprof_compute_flat_info($run1_data, $totals_1);
            $symbol_tab2 = $this->xhprof_compute_flat_info($run2_data, $totals_2);
        } else {
            $symbol_tab = $this->xhprof_compute_flat_info($run1_data, $totals);
        }

        $run1_txt = sprintf(
            "<b>Run #%s:</b> %s",
            $run1,
            $run1_desc
        );

        $base_url_params = $this->xhprof_array_unset(
            $this->xhprof_array_unset(
                $url_params,
                'symbol'
            ),
            'all'
        );

        $top_link_query_string = "$base_url?".http_build_query($base_url_params);

        if ($diff_mode) {
//            $diff_text       = "Diff";
//            $base_url_params = $this->xhprof_array_unset($base_url_params, 'run1');
//            $base_url_params = $this->xhprof_array_unset($base_url_params, 'run2');
//            $run1_link       = xhprof_render_link(
//                'View Run #'.$run1,
//                "$base_url?".
//                http_build_query(
//                    xhprof_array_set(
//                        $base_url_params,
//                        'run',
//                        $run1
//                    )
//                )
//            );
//            $run2_txt        = sprintf(
//                "<b>Run #%s:</b> %s",
//                $run2,
//                $run2_desc
//            );
//
//            $run2_link = xhprof_render_link(
//                'View Run #'.$run2,
//                "$base_url?".
//                http_build_query(
//                    xhprof_array_set(
//                        $base_url_params,
//                        'run',
//                        $run2
//                    )
//                )
//            );
        } else {
            $diff_text = "Run";
        }

        // set up the action links for operations that can be done on this report
        $links    = array();
        $links [] = xhprof_render_link(
            "View Top Level $diff_text Report",
            $top_link_query_string
        );

//        if ($diff_mode) {
//            $inverted_params         = $url_params;
//            $inverted_params['run1'] = $url_params['run2'];
//            $inverted_params['run2'] = $url_params['run1'];
//
//            // view the different runs or invert the current diff
//            $links [] = $run1_link;
//            $links [] = $run2_link;
//            $links [] = xhprof_render_link(
//                'Invert '.$diff_text.' Report',
//                "$base_url?".
//                http_build_query($inverted_params)
//            );
//        }

//        // lookup function typeahead form
//        $links [] = '<input class="function_typeahead" '.
//                    ' type="input" size="40" maxlength="100" />';
//
//        echo xhprof_render_actions($links);


//        echo
//            '<dl class=phprof_report_info>'.
//            '  <dt>'.$diff_text.' Report</dt>'.
//            '  <dd>'.($diff_mode
//                ?
//                $run1_txt.'<br><b>vs.</b><br>'.$run2_txt
//                :
//                $run1_txt).
//            '  </dd>'.
//            '  <dt>Tip</dt>'.
//            '  <dd>Click a function name below to drill down.</dd>'.
//            '</dl>'.
//            '<div style="clear: both; margin: 3em 0em;"></div>';

        // data tables
        full_report($url_params, $symbol_tab, $sort, $run1, $run2);
    }


    /**
     * Set one key in an array and return the array
     *
     * @author Kannan
     */
    public function xhprof_array_set($arr, $k, $v)
    {
        $arr[$k] = $v;

        return $arr;
    }

    /**
     * Removes/unsets one key in an array and return the array
     *
     * @author Kannan
     */
    public function xhprof_array_unset($arr, $k)
    {
        unset($arr[$k]);

        return $arr;
    }


}