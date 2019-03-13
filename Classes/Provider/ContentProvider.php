<?php

namespace Lfda\Headless\Provider;


use Lfda\Headless\Service\MappingService;
use Lfda\Headless\Service\SelectionService;

class ContentProvider extends BaseProvider
{

    public function __construct()
    {
        $this->table = 'tt_content';
    }

    public function fetchData()
    {
        $selection = SelectionService::prepare($this->getConfiguration('selection/defaults', []));
        $selection['pid'] = SelectionService::make($this->getArgument('page', 0));

        $contents = $this->fetch($this->table, array_keys($this->getConfiguration('mapping')), $selection);
        $mapping = $this->getConfiguration('mapping', []);
        $result = [];

        while ($row = $contents->fetch()) {
            $transformed = MappingService::transform($row, $mapping, $this->table);

//            // rendering
//            if ($render_config) {
//                foreach ($render_config as $target_key => $config) {
//                    if ($this->matches($config, $transformed)) {
//                        $renderer = GeneralUtility::makeInstance($config['renderer']);
//                        $transformed[$target_key] = $renderer->execute($transformed);
//
//                        // $newsController = GeneralUtility::makeInstance(NewsController::class);
//                        // $transformed[$target_key] = $newsController->getSettings();
//                        /*
//                        try {
//                            $renderer = new $config['renderer'];
//                            $transformed[$target_key] = $renderer->execute($transformed);
//                        } catch (Exception $exception) {
//                            $transformed[$target_key] = 'Can\'t find renderer!!!';
//                        }
//                        */
//                    } else {
//                        $transformed[$target_key] = false;
//                    }
//                }
//            }

            $result[] = $transformed;
        }

        return $result;

    }

}
