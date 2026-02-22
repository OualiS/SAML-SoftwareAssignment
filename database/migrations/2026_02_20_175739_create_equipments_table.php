<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	/**
	 * Run the migrations.
	 */
	public function up(): void
	{
		DB::unprepared(<<<'SQL'
			CREATE TABLE `equipments` (
				`Equipment` VARCHAR(128) NOT NULL COLLATE 'utf8mb4_unicode_ci',
				`Material` VARCHAR(191) NULL DEFAULT NULL COLLATE 'utf8mb4_unicode_ci',
				`MaterialWithoutFet` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci',
				`Description` VARCHAR(191) NULL DEFAULT NULL COLLATE 'utf8mb4_unicode_ci',
				`IH09Description` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8mb4_unicode_ci',
				`Room` VARCHAR(191) NULL DEFAULT NULL COLLATE 'utf8mb4_unicode_ci',
				`Plant` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8mb4_unicode_ci',
				`Location` VARCHAR(191) NULL DEFAULT NULL COLLATE 'utf8mb4_unicode_ci',
				`Sloc` VARCHAR(191) NULL DEFAULT NULL COLLATE 'utf8mb4_unicode_ci',
				`SuperEq` VARCHAR(128) NULL DEFAULT NULL COLLATE 'utf8mb4_unicode_ci',
				`ManufactSerialNumber` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci',
				`SerNo` VARCHAR(191) NULL DEFAULT NULL COLLATE 'utf8mb4_unicode_ci',
				`UserStatus` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci',
				`SystemStatus` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci',
				`Dimensions` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8mb4_unicode_ci',
				`CleaningCounter_limit` INT(11) NOT NULL DEFAULT '0',
				`CleaningCounter_current` INT(11) NOT NULL DEFAULT '0',
				`ToolCompetence` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci',
				`NextCertDate` DATE NULL DEFAULT NULL,
				`NextCalDate` DATE NULL DEFAULT NULL,
				`NextCtrlDate` DATE NULL DEFAULT NULL,
				`NEN3140Int` INT(11) NOT NULL,
				`MaintInt` INT(11) NOT NULL,
				`NextNEN3140Date` DATE NULL DEFAULT NULL,
				`CalInt` INT(11) NOT NULL,
				`NextMaintDate` DATE NULL DEFAULT NULL,
				`CertInt` INT(11) NOT NULL,
				`CtrlInt` INT(11) NOT NULL,
				`ExempEndDate` DATE NULL DEFAULT NULL,
				`Min_CALD_Date` DATE NULL DEFAULT NULL,
				`GrossWeight` FLOAT NULL DEFAULT NULL,
				`current_status` VARCHAR(1000) NOT NULL COLLATE 'utf8mb4_unicode_ci',
				`needed_time` TIMESTAMP NULL DEFAULT NULL,
				`return_time` TIMESTAMP NULL DEFAULT NULL,
				`workcenter` VARCHAR(1000) NOT NULL COLLATE 'utf8mb4_unicode_ci',
				`material_status` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci',
				`StockType` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8mb4_unicode_ci',
				`SpecialStock` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8mb4_unicode_ci',
				`CreatedOn` TIMESTAMP NULL DEFAULT NULL,
				`CreatedBy` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci',
				`ChangedOn` TIMESTAMP NULL DEFAULT NULL,
				`ChangedBy` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci',
				PRIMARY KEY (`Equipment`) USING BTREE,
				INDEX `Material` (`Material`) USING BTREE
			)
			COLLATE='utf8mb4_unicode_ci'
			ENGINE=InnoDB;
			SQL
		);
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('equipments');
	}
};
