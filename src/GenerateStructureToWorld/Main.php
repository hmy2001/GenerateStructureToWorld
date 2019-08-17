<?php

declare(strict_types=1);

namespace GenerateStructureToWorld;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\nbt\LittleEndianNBTStream;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use const pocketmine\RESOURCE_PATH;

class Main extends PluginBase{
	private $legacyIdMap, $colorData = [], $woodColorData = [], $stoneTypeData = [], $cobbleStoneWallData = [], $stoneSlabTypeData = [];

	public function onEnable(){
		$this->legacyIdMap = json_decode(file_get_contents(RESOURCE_PATH . "vanilla/block_id_map.json"), true);
		$this->generateBlockStateData();
	}

	private function generateBlockStateData(){
		$this->colorData = [
			"white",
			"orange",
			"magenta",
			"light_blue",
			"yellow",
			"lime",
			"pink",
			"gray",
			"silver",
			"cyan",
			"purple",
			"blue",
			"brown",
			"green",
			"red",
			"black",
		];
		$this->woodColorData = [//TODO: check strict
			"oak",//normal?
			"spruce",
			"birch",
			"jungle",
			"acacia",
			"dark_oak"
		];
		$this->stoneTypeData = [
			"stone",
			"granite",
			"granite_smooth",
			"diorite",
			"diorite_smooth",
			"andesite",
			"andesite_smooth"
		];
		$this->cobbleStoneWallData = [
			"cobblestone",
			"mossy_cobblestone"
		];
		$this->stoneSlabTypeData = [
			"smooth_stone",
			"sand_stone",
			"wooden",
			"cobblestone",
			"brick",
			"stone_brick",
			"quartz",
			"nether_brick"
		];
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool{
		$isPlayer = ($sender instanceof Player);
		switch($label){
			case "generate":
				if($isPlayer){
					if(isset($args[0])){
						$directory = $this->getDataFolder().$args[0].DIRECTORY_SEPARATOR;
						$directoryName = $args[0];
						if(is_dir($directory)){
							if(file_exists($directory."part1.mcstructure")){
								$this->generateStructure($directoryName, $directory);
							}else{
								$sender->sendMessage("Not structure directory: ".$directoryName);
								$sender->sendMessage("You must place part*.mcstructure. * is the number. one-based number.");
							}
						}else{
							$sender->sendMessage("Not exist directory: ".$directoryName);
						}
					}else{
						return false;
					}
				}else{
					$sender->sendMessage("Only Player");
				}
			break;
			case "create":
				if(isset($args[0])){
					$directory = $this->getDataFolder().$args[0].DIRECTORY_SEPARATOR;
					$directoryName = $args[0];
					if(is_dir($directory)){
						if(file_exists($directory."part1.mcstructure")){
							$this->createStructureJson($directoryName, $directory);
						}else{
							$sender->sendMessage("Not structure directory: ".$directoryName);
							$sender->sendMessage("You must place part*.mcstructure. * is the number. one-based number.");
						}
					}else{
						$sender->sendMessage("Not exist directory: ".$directoryName);
					}
				}else{
					return false;
				}
			break;
		}

		return true;
	}

	private function generateStructure(string $directoryName, string $directory){//TODO: startX, startY, startZ
		$this->getServer()->broadcastMessage("Generating structure json: ".$directoryName);
		$startX = 300;
		$startY = 50;
		$startZ = 255;
		$structureData = $this->getStructureData($directory);
		foreach($structureData as $data){
			$x = $startX + $data[0];
			$y = $startY + $data[1];
			$z = $startZ + $data[2];
			$this->getServer()->getDefaultLevel()->setBlockIdAt($x, $y, $z, $data[3]);
			$this->getServer()->getDefaultLevel()->setBlockDataAt($x, $y, $z, $data[4]);
			//TODO: update light
		}
		$this->getServer()->broadcastMessage("Generated structure json: ".$directoryName);
	}

	private function createStructureJson(string $directoryName, string $directory){
		$this->getServer()->broadcastMessage("Creating structure json: ".$directoryName);
		$structureData = $this->getStructureData($directory);
		file_put_contents($this->getDataFolder().$directoryName.".json", json_encode($structureData, JSON_PRETTY_PRINT));
		$this->getServer()->broadcastMessage("Created structure json: ".$directoryName);
	}

	private function getStructureData(string $directory): array{
		$minX = $minY = $minZ = PHP_INT_MAX;
		$structureData = [];
		foreach(glob($directory."part*.mcstructure") as $fileName){
			$stream = new LittleEndianNBTStream();
			$nbt = $stream->read(file_get_contents($fileName));
			/** @var CompoundTag $nbt */
			$nbtData = LittleEndianNBTStream::toArray($nbt);
			$startX = $nbtData["structure_world_origin"][0];
			$startY = $nbtData["structure_world_origin"][1];
			$startZ = $nbtData["structure_world_origin"][2];

			if($minX > $startX){
				$minX = $startX;
			}
			if($minY > $startY){
				$minY = $startY;
			}
			if($minZ > $startZ){
				$minZ = $startZ;
			}

			$index = 0;
			for($x = 0; $x < $nbtData["size"][0]; $x++){
				for($y = 0; $y < $nbtData["size"][1]; $y++){
					for($z = 0; $z < $nbtData["size"][2]; $z++){
						$paletteIndex = $nbtData["structure"]["block_indices"][0][$index];
						$paletteData = $nbtData["structure"]["palette"]["default"]["block_palette"][$paletteIndex];
						$blockData = $this->getBlockDataFromBlockPalette($paletteData);
						$structureData[] = [$startX + $x, $startY + $y, $startZ + $z, $blockData[0], $blockData[1]];
						$index++;
					}
				}
			}
		}

		/*var_dump([$minX, $minY, $minZ]);

		$minX = abs($minX);
		$minY = abs($minY);
		$minZ = abs($minZ);

		var_dump([$minX, $minY, $minZ]);*/

		$sortedStructureData = [];
		foreach($structureData as $data){
			$sortedStructureData[] = [$data[0] - $minX, $data[1] + $minY, $data[2] - $minZ, $data[3], $data[4]];
		}

		return $sortedStructureData;
	}

	private function getBlockDataFromBlockPalette(array $paletteData): array{
		$blockId = 0;
		$blockDamage = 0;
		$blockName = $paletteData["name"];
		if($blockName === "minecraft:structure_block"){
			$blockId = 0;//Air
		}else{
			if(isset($this->legacyIdMap[$blockName])){
				$blockId = $this->legacyIdMap[$blockName];
				switch($blockName){
					case "minecraft:stone":
						if(isset($paletteData["states"]["stone_type"])){
							if(($key = array_search($paletteData["states"]["stone_type"], $this->stoneTypeData, true)) !== false){
								$blockDamage = $key;
							}else{
								var_dump([$blockName => $paletteData["states"]]);
							}
						}
					break;
					case "minecraft:dirt":
						if(isset($paletteData["states"]["dirt_type"])){
							if($paletteData["states"]["dirt_type"] === "normal"){
								$blockDamage = 0;
							}else{
								var_dump([$blockName => $paletteData["states"]]);
							}
						}
					break;
					case "minecraft:water":
					case "minecraft:lava":
						/*if(isset($paletteData["states"]["liquid_depth"])){
							//TODO: implement it
						}*/
					break;
					case "minecraft:wool":
					case "minecraft:stained_hardened_clay":
						if(isset($paletteData["states"]["color"])){
							if(($key = array_search($paletteData["states"]["color"], $this->colorData, true)) !== false){
								$blockDamage = $key;
							}else{
								var_dump([$blockName => $paletteData["states"]]);
							}
						}
					break;
					case "minecraft:wooden_slab":
					case "minecraft:fence":
					case "minecraft:planks":
					case "minecraft:leaves":
						if(isset($paletteData["states"]["wood_type"])){
							if(($key = array_search($paletteData["states"]["wood_type"], $this->woodColorData, true)) !== false){
								$blockDamage = $key;
							}else{
								var_dump([$blockName => $paletteData["states"]]);
							}
						}
					break;
					case "minecraft:stone_slab":
						if(isset($paletteData["states"]["stone_slab_type"])){
							if(($key = array_search($paletteData["states"]["stone_slab_type"], $this->stoneSlabTypeData, true)) !== false){
								$blockDamage = $key;
							}else{
								var_dump([$blockName => $paletteData["states"]]);
							}
						}
						//TODO: top bit
					break;
					case "minecraft:stone_slab2":
						if(isset($paletteData["states"]["stone_slab_type_2"])){
							if($paletteData["states"]["stone_slab_type_2"] === "mossy_cobblestone"){
								$blockDamage = 5;
							}else{
								var_dump([$blockName => $paletteData["states"]]);
							}
						}
						//TODO; top bit
					break;
					case "minecraft:cobblestone_wall":
						if(isset($paletteData["states"]["wall_block_type"])){
							if(($key = array_search($paletteData["states"]["wall_block_type"], $this->cobbleStoneWallData, true)) !== false){
								$blockDamage = $key;
							}else{
								var_dump([$blockName => $paletteData["states"]]);
							}
						}
					break;
					case "minecraft:smooth_stone"://TODO: convert
						//$blockId = 434;
						//$blockDamage = 0;
					break;
					case "minecraft:mossy_cobblestone_stairs"://TODO: convert
						//$blockId = 434;
						var_dump([$blockName => $paletteData["states"]]);
					break;
					case "minecraft:quartz_block":
						//TODO: convert
					break;
					case "minecraft:coral":
					case "minecraft:kelp":
						$blockId = 0;
					break;
					case "minecraft:bedrock":
					break;
					default:
						if(!empty($paletteData["states"])){
							var_dump([$blockName => $paletteData["states"]]);
						}
					break;
				}
			}
		}

		return [$blockId, $blockDamage];
	}


}