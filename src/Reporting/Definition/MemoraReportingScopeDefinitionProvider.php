<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of Memora for BASE3 Framework.
 *
 * Memora is a modular XRM/CRM backend and knowledge graph for BASE3.
 * It provides structured entities, relations, metadata and tags.
 * ResourceFoundation contracts govern its shared entity services.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de
 * https://github.com/ddbase3/Memora
 **********************************************************************/

namespace Base3\Memora\Reporting\Definition;

use Base3\ResourceFoundation\Api\IReportingScopeDefinitionProvider;
use Base3\ResourceFoundation\Dto\ReportingScopeDefinition;

final class MemoraReportingScopeDefinitionProvider implements IReportingScopeDefinitionProvider {

	public static function getName(): string {
		return 'memorareportingscopedefinitionprovider';
	}

	public function getReportingScopeDefinition(): ReportingScopeDefinition {
		return new ReportingScopeDefinition(
			id: 'memora',
			label: 'Memora',
			querySchemaScopes: ['memora']
		);
	}
}
