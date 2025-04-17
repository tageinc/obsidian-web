<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVersion;
use App\Models\License;
use App\Models\Software;
use App\Models\Company;
use App\Models\Config;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
	/**
	 * Seed the application's database.
	 *
	 * @return void
	 */
	public function run()
	{
		$now = now();

		Schema::disableForeignKeyConstraints();

		// CONFIG
		Config::insert([
			[
				'key' => 'software_ping_timeout',
				'value' => '15'
			]
		]);

		// COMPANIES
		Company::create([
			'name' => 'Hydronium',
			'address' => 'Address of Hydronium',
			'admin_id' => 1,
			'phone_office' => '123456789',
			'phone_office_ext' => '1234',
			'phone_mobile' => '123456789',
			'city' => 'Calexico',
			'state' => 'California',
			'country' => 'US',
			'zip_code' => '92231',
			'url' => 'www.hydronium.com',
			'customer_id' => '60cd1a10195cd',
			'created_at' => $now,
			'updated_at' => $now
		]);

		// THIS COMPANY IS THE FIRST ONE ON TAGCCORP
		Company::create([
			'name' => 'The Alliance Group Enterprise Inc.',
			'address' => '3699 Wilshire Boulevard',
			'address_2' => 'Suite 1210',
			'admin_id' => 3,
			'phone_office' => '5628791727',
			'phone_mobile' => '5628791727',
			'city' => 'Los Angeles',
			'state' => 'CA',
			'country' => 'US',
			'zip_code' => '90010',
			'url' => 'www.tagccorp.com',
			'customer_id' => '60cd388ec8629',
			'created_at' => '2021-06-19 00:21:34',
			'updated_at' => '2021-06-19 00:21:34'
			
			//--
		]);

		// USERS
		User::insert([
			[
				'name' => 'user',
				'email' => 'user@hydronium.com',
				'password' => bcrypt('hydropass'),
				'company_id' => 1,
				'remember_token' => Str::random(10),
				'created_at' => $now,
				'updated_at' => $now,
				'email_verified_at' => $now
			],
			[
				'name' => 'user_test',
				'email' => 'user_test@hydronium.com',
				'password' => bcrypt('user_test'),
				'company_id' => 1,
				'remember_token' => Str::random(10),
				'created_at' => $now,
				'updated_at' => $now,
				'email_verified_at' => $now
			]
		]);

		// THIS USER IS THE FIRST ONE ON TAGCCORP
		User::create([
			'name' => 'Adrian Troncoso',
			'email' => 'adrian.troncoso@tagccorp.com',
			'password' => '$2y$10$rPCT8ko/HRHLJRjskwTOJu6QoNPQ36zcVBT3xSmzUcnMlyTCnGLvG',
			'company_id' => 2,
			'remember_token' => '9qcyH03GiL',
			'created_at' => '2021-06-19 00:21:34',
			'updated_at' => '2021-06-19 06:42:21',
			'last_login_at' => '2021-06-19 06:42:21',
			'last_logout_at' => '2021-06-19 01:09:41'
		]);

		// PRODUCTS
		Software::insert([
			[
				'name' => 'ClientConnect',
				'version' => '1.0',
				'slug' => Str::slug('ClientConnect'),
				'owner' => 'Soft',
				'key' => Hash::make(env('CLIENTCONNECT_KEY')),
				'description' => 'A description of the software.',
				'change_log' => 'None.',
				'url' => 'https://hydronium-la.com/software/client_connect_installer.exe',
				'icon_url' => 'https://hydronium-la.com/software/clientconnect_icon.png',
				'created_at' => $now,
				'updated_at' => $now
			],
			[
				'name' => 'Hydronium',
				'version' => '1.0',
				'slug' => Str::slug('Hydronium'),
				'owner' => 'Soft',
				'key' => Hash::make(env('HYDRONIUM_KEY')),
				'description' => 'A description of the software.',
				'change_log' => 'None.',
				'url' => 'https://hydronium-la.com/software/hydronium_installer.exe',
				'icon_url' => 'https://hydronium-la.com/software/hydronium_icon.png',
				'created_at' => $now,
				'updated_at' => $now
			],
		]);

		Product::insert([
			[
				'name' => 'Quaterly',
				'slug' => 'quaterly',
				'duration_days' => 30 * 4,
				'price' => 795,
				'quantity' => 1,
				'software_id' => 2
			],
			[
				'name' => 'Annually',
				'slug' => 'anual',
				'duration_days' => 30 * 12,
				'price' => 2695,
				'quantity' => 1,
				'software_id' => 2
			],
			[
				'name' => '3 Group Quaterly',
				'slug' => 'quaterly_3',
				'duration_days' => 30 * 4,
				'price' => 2195,
				'quantity' => 3,
				'software_id' => 2
			],
			[
				'name' => '3 Group Annually',
				'slug' => 'anual_3',
				'duration_days' => 30 * 12,
				'price' => 7895,
				'quantity' => 3,
				'software_id' => 2
			],
			[
				'name' => '5 Group Quaterly',
				'slug' => 'quaterly_5',
				'duration_days' => 30 * 4,
				'price' => 3795,
				'quantity' => 5,
				'software_id' => 2
			],
			[
				'name' => '5 Group Annually',
				'slug' => 'anual_5',
				'duration_days' => 30 * 12,
				'price' => 12195,
				'quantity' => 5,
				'software_id' => 2
			]
		]);

		Schema::enableForeignKeyConstraints();

		// Orders
		Order::insert([
			[
				'company_id' => 1,
				'products' => '[{"product":{"name":"Quaterly","duration_days":"120","price":"795","quantity":"1"},"quantity":"1"}]',
				'transaction_id' => '0',
				'total' => 795,
				'created_at' => '2021-06-18 00:00:00',
				'updated_at' => '2021-06-18 00:00:00'
			],
			// THIS ORDER IS THE FIRST ONE ON TAGCCORP
			[
				'company_id' => 2,
				'products' => '[{"product":{"name":"Quaterly","duration_days":"120","price":"795","quantity":"1"},"quantity":"1"}]',
				'transaction_id' => '63099755243',
				'total' => 795,
				'created_at' => '2021-06-19 00:54:29',
				'updated_at' => '2021-06-19 00:54:29'
			]
		]);

		$product = Product::find(1);
		License::createForUser(User::find(1), $product, 1);
		License::createForUser(User::find(2), $product, 1);

		// THIS LICENSE IS THE FIRST ONE ON TAGCCORP
		License::create([
			'key' => '56d37aee-eca5-4007-a829-8264aaee371c',
			'product_id' => 1,
			'company_id' => 2,
			'user_id' => 3,
			'order_id' => 2,
			'subscription_id' => '1',
			'created_at' => '2021-06-19 00:54:29',
			'updated_at' => '2021-06-19 01:06:08'		
		]);
	}
}
