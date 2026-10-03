<?php
declare(strict_types=1);

/**
 * Deterministic project -> sub-indicator attribution repair.
 *
 * The catalogue is programme-wide, but the reporting form must only expose
 * sub-indicators attributed to the selected child project. This function is
 * intentionally separate from the broader schema bootstrap so a failure in
 * another optional schema element cannot leave the attribution table empty.
 */
function ensure_project_subindicator_links(): void {
    try {
        $pdo = db();

        $mapping = [
            '1.1'=>['10279'=>'O1.1','10472'=>'O1.1','10848'=>'O1.1','10261'=>'O.I.2','10258'=>'O1.1/1.2','10267'=>'O.1.2/O.1.1'],
            '1.2'=>['10279'=>'O1.1','10472'=>'O1.1','10848'=>'O1.1','10261'=>'O.I.2','10258'=>'O1.1/1.2','10267'=>'O.1.2/O.1.1'],
            '2.1'=>['10267'=>'O2.2.1/2.3.1','10279'=>'O2.2 – Activity 2.2.3','10472'=>'O2.1 – 2.1.1','10261'=>'O2.1','10848'=>'O2.1 – activity 2.1.1','10258'=>'O2.1'],
            '2.2'=>['10267'=>'PPP strategy under O2.1','10279'=>'O2.1/O2.2','10472'=>'O2.1/O2.2','10261'=>'O2.2','10848'=>'O2.1/O2.2.1','10258'=>'O2.1'],
            '2.3'=>['10267'=>'O2.4.2','10848'=>'O2.2 – Activity 2.2.4','10258'=>'O2.4'],
            '2.4'=>['10267'=>'O2.2','10279'=>'O2.1/O2.2','10472'=>'O2.1/O2.2','10261'=>'Activity 2.4.5/2.4.12','10848'=>'O2.2 – Activity 2.2.2'],
            '2.5'=>['10279'=>'O2.1 – Activity 2.1.2','10472'=>'O2.1/O2.2','10261'=>'O2.4','10848'=>'O2.1/O2.2'],
            '2.6'=>['10261'=>'O2.1/O2.3.1'],
            '3.1'=>['10267'=>'O3.2/O3.3','10261'=>'Ind.10'],
            '3.2'=>['10267'=>'O3.2, Ind.20','10279'=>'O3.3','10472'=>'O3.3/O2.2','10261'=>'Activity 2.4.5/2.4.12','10848'=>'O2.2, Ind.5'],
            '3.3'=>['10267'=>'O1.1','10279'=>'O3.1','10472'=>'O3.1','10261'=>'Ind.12','10258'=>'indirect – O3.1–3.8/4.1–4.3'],
            '3.4'=>['10279'=>'O3.3, Activity 3.3.1','10472'=>'O3.3, Activity 3.3.1','10261'=>'O3.3, Activity 3.1.2','10848'=>'O3.3, Activity 3.3.1/3.3.2/3.3.3'],
            '3.5'=>['10261'=>'Ind.11','10848'=>'O3.3'],
            '3.6'=>['10472'=>'O3.3','10279'=>'O3.3 + Activity 3.3.1','10848'=>'O3.3'],
            '3.7'=>['10279'=>'O3.3, Activity 3.3.1','10472'=>'O3.3, Activity 3.3.1','10261'=>'O3.3, Activity 3.1.2','10848'=>'O3.3, Activity 3.3.1/3.3.2/3.3.3'],
            '3.8'=>['10267'=>'O2.2/O3.1/O3.4/O4.2-4.3','10279'=>'O2.2/O3.2/O4.1','10472'=>'O2.2/O3.2/O4.1','10261'=>'Ind.7/13/14','10848'=>'O2.2/O3.1/O3.2/O3.3/O4.1','10258'=>'O5.1/5.2/5.3'],
            '4.1'=>['10267'=>'knowledge products','10279'=>'knowledge products','10472'=>'knowledge products','10261'=>'knowledge products','10258'=>'knowledge products','10848'=>'knowledge products'],
            '4.1.1'=>['10279'=>'O4.1','10261'=>'Outcome 4 – Ind.13/14','10258'=>'Comp.5.3/5.4'],
            '4.2'=>['10267'=>'All child projects','10279'=>'All child projects','10472'=>'All child projects','10261'=>'All child projects','10848'=>'All child projects','10258'=>'All child projects'],
            '4.3'=>[],
            '4.4'=>['10267'=>'All child projects','10279'=>'All child projects','10472'=>'All child projects','10261'=>'All child projects','10848'=>'All child projects','10258'=>'All child projects'],
            '4.5'=>['10267'=>'CCKM-led in coordination with regional child projects','10279'=>'CCKM-led in coordination with regional child projects','10472'=>'CCKM-led in coordination with regional child projects','10261'=>'CCKM-led in coordination with regional child projects','10848'=>'CCKM-led in coordination with regional child projects','10258'=>'CCKM-led in coordination with regional child projects'],
            '4.6'=>[],
            '4.7'=>['10267'=>'All child projects','10279'=>'All child projects','10472'=>'All child projects','10261'=>'All child projects','10848'=>'All child projects','10258'=>'All child projects'],
        ];

        $gebAssoc = [
            '1.1'=>['GEF #9.4','GEF #10.1'],'1.2'=>['GEF #11'],
            '2.1'=>['GEF #9','GEF #9.1','GEF #9.2'],'2.2'=>['GEF #9.4','GEF #9.5'],'2.3'=>['GEF #9.5'],'2.4'=>['GEF #11'],
            '2.5'=>['GEF #9','GEF #9.1','GEF #9.2'],'2.6'=>['GEF #9','GEF #9.1','GEF #9.2'],
            '3.1'=>['GEF #5.3','GEF #9'],'3.2'=>['GEF #5.3','GEF #9'],'3.3'=>['GEF #9.6','GEF #5.3'],
            '3.4'=>['GEF #9.6','GEF #5.3'],'3.5'=>['GEF #10','GEF #10.1'],'3.6'=>['GEF #10.2','GEF #5.3'],
            '3.7'=>['GEF #5.3','GEF #9.6'],'3.8'=>['GEF #11'],
            '4.1'=>['GEF #11'],'4.1.1'=>['GEF #11'],'4.2'=>['GEF #11'],'4.3'=>['GEF #11'],
            '4.4'=>['GEF #11'],'4.5'=>['GEF #11'],'4.6'=>['GEF #11'],'4.7'=>['GEF #11']
        ];

        $subrows=[];
        foreach($pdo->query("SELECT id,code FROM sub_indicators WHERE active=1") as $r) {
            $subrows[(string)$r['code']]=(int)$r['id'];
        }
        $projrows=[];
        foreach($pdo->query("SELECT id,project_code FROM projects WHERE active=1") as $r) {
            $projrows[(string)$r['project_code']]=(int)$r['id'];
        }
        $gebids=[];
        foreach($pdo->query("SELECT id,code FROM indicators WHERE active=1") as $r) {
            $gebids[(string)$r['code']]=(int)$r['id'];
        }

        $upsert=$pdo->prepare("
            INSERT INTO project_sub_indicator_links
              (project_id,sub_indicator_id,geb_indicator_id,project_logframe_reference,mapping_note,active)
            VALUES (?,?,?,?,?,1)
            ON DUPLICATE KEY UPDATE
              project_logframe_reference=VALUES(project_logframe_reference),
              mapping_note=VALUES(mapping_note),
              active=1
        ");
        $findNull=$pdo->prepare("
            SELECT id FROM project_sub_indicator_links
            WHERE project_id=? AND sub_indicator_id=? AND geb_indicator_id IS NULL
            ORDER BY id LIMIT 1
        ");
        $updateNull=$pdo->prepare("
            UPDATE project_sub_indicator_links
            SET project_logframe_reference=?,mapping_note=?,active=1
            WHERE id=?
        ");
        $insertNull=$pdo->prepare("
            INSERT INTO project_sub_indicator_links
              (project_id,sub_indicator_id,geb_indicator_id,project_logframe_reference,mapping_note,active)
            VALUES (?,?,?,?,?,1)
        ");

        foreach($mapping as $scode=>$projects) {
            if(!isset($subrows[$scode])) continue;
            foreach($projects as $pcode=>$reference) {
                if(!isset($projrows[$pcode])) continue;

                $didGeb=false;
                foreach(($gebAssoc[$scode] ?? []) as $gcode) {
                    if(!isset($gebids[$gcode])) continue;
                    $upsert->execute([
                        $projrows[$pcode],$subrows[$scode],$gebids[$gcode],$reference,
                        'Framework child-project attribution; GEB linkage is configurable and traceable.'
                    ]);
                    $didGeb=true;
                }

                if(!$didGeb) {
                    $findNull->execute([$projrows[$pcode],$subrows[$scode]]);
                    $existing=$findNull->fetchColumn();
                    if($existing) {
                        $updateNull->execute([
                            $reference,
                            'Framework child-project attribution; no direct GEB linkage specified.',
                            (int)$existing
                        ]);
                    } else {
                        $insertNull->execute([
                            $projrows[$pcode],$subrows[$scode],null,$reference,
                            'Framework child-project attribution; no direct GEB linkage specified.'
                        ]);
                    }
                }
            }
        }
    } catch(Throwable $e) {
        // The form remains usable even if an optional repair cannot run.
        // The diagnostic page exposes the counts.
    }
}
