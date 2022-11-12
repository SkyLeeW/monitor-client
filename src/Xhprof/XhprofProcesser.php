<?php

namespace Skylee\MonitorClient\Xhprof;

class XhprofProcesser extends XphrofData
{

    public function start($rowData)
    {
        $total = [];
        $this->initMetrics($rowData, "", "");

        $dataC   = [];
        $datafff = $this->xhprof_compute_flat_info($rowData, $dataC);
        dd($datafff);
    }


    public function initMetrics($xhprof_data, $rep_symbol, $sort, $diff_report = false)
    {
        $diff_mode = $diff_report;

        if ( ! empty($sort)) {
            if (array_key_exists($sort, $this->sortable_columns)) {
                $this->sort_col = $sort;
            }
        }

        // For C++ profiler runs, walltime attribute isn't present.
        // In that case, use "samples" as the default sort column.
        if ( ! isset($xhprof_data["main()"]["wt"])) {
            if ($this->sort_col == "wt") {
                $this->sort_col = "samples";
            }

            // C++ profiler data doesn't have call counts.
            // ideally we should check to see if "ct" metric
            // is present for "main()". But currently "ct"
            // metric is artificially set to 1. So, relying
            // on absence of "wt" metric instead.
            $this->display_calls = false;
        } else {
            $this->display_calls = true;
        }

        // parent/child report doesn't support exclusive times yet.
        // So, change sort hyperlinks to closest fit.
        if ( ! empty($rep_symbol)) {
            $this->sort_col = str_replace("excl_", "", $this->sort_col);
        }

        if ($this->display_calls) {
            $this->stats = array("fn", "ct", "Calls%");
        } else {
            $this->stats = array("fn");
        }

        $this->pc_stats = $this->stats;

        $possible_metrics = $this->xhprof_get_possible_metrics();
        foreach ($possible_metrics as $metric => $desc) {
            if (isset($xhprof_data["main()"][$metric])) {
                $this->metrics[] = $metric;
                // flat (top-level reports): we can compute
                // exclusive metrics reports as well.
                $this->stats[] = $metric;
                $this->stats[] = "I".$desc[0]."%";
                $this->stats[] = "excl_".$metric;
                $this->stats[] = "E".$desc[0]."%";

                // parent/child report for a function: we can
                // only breakdown inclusive times correctly.
                $this->pc_stats[] = $metric;
                $this->pc_stats[] = "I".$desc[0]."%";
            }
        }
    }


    public function xhprof_compute_flat_info($raw_data, &$overall_totals)
    {
        $metrics = $this->xhprof_get_metrics($raw_data);


        $overall_totals = array(
            "ct"      => 0,
            "wt"      => 0,
            "ut"      => 0,
            "st"      => 0,
            "cpu"     => 0,
            "mu"      => 0,
            "pmu"     => 0,
            "samples" => 0,
        );

        // compute inclusive times for each function
        $symbol_tab = $this->xhprof_compute_inclusive_times($raw_data);

        /* total metric value is the metric value for "main()" */
        foreach ($metrics as $metric) {
            $overall_totals[$metric] = $symbol_tab["main()"][$metric];
        }

        /*
         * initialize exclusive (self) metric value to inclusive metric value
         * to start with.
         * In the same pass, also add up the total number of function calls.
         */
        foreach ($symbol_tab as $symbol => $info) {
            foreach ($metrics as $metric) {
                $symbol_tab[$symbol]["excl_".$metric] = $symbol_tab[$symbol][$metric];
            }
            if ($this->display_calls) {
                /* keep track of total number of calls */
                $overall_totals["ct"] += $info["ct"];
            }
        }

        /* adjust exclusive times by deducting inclusive time of children */
        foreach ($raw_data as $parent_child => $info) {
            list($parent, $child) = $this->xhprof_parse_parent_child($parent_child);

            if ($parent) {
                foreach ($metrics as $metric) {
                    // make sure the parent exists hasn't been pruned.
                    if (isset($symbol_tab[$parent])) {
                        $symbol_tab[$parent]["excl_".$metric] -= $info[$metric];
                    }
                }
            }
        }


        return $symbol_tab;
    }


    public function xhprof_compute_inclusive_times($raw_data)
    {
        $display_calls = $this->display_calls;

        $metrics = $this->xhprof_get_metrics($raw_data);

        $symbol_tab = array();

        /*
         * First compute inclusive time for each function and total
         * call count for each function across all parents the
         * function is called from.
         */
        foreach ($raw_data as $parent_child => $info) {
            list($parent, $child) = $this->xhprof_parse_parent_child($parent_child);

            if ($parent == $child) {
                return;
            }

            if ( ! isset($symbol_tab[$child])) {
                if ($display_calls) {
                    $symbol_tab[$child] = array("ct" => $info["ct"]);
                } else {
                    $symbol_tab[$child] = array();
                }
                foreach ($metrics as $metric) {
                    $symbol_tab[$child][$metric] = $info[$metric];
                }
            } else {
                if ($display_calls) {
                    /* increment call count for this child */
                    $symbol_tab[$child]["ct"] += $info["ct"];
                }

                /* update inclusive times/metric for this child  */
                foreach ($metrics as $metric) {
                    $symbol_tab[$child][$metric] += $info[$metric];
                }
            }
        }

        return $symbol_tab;
    }


}