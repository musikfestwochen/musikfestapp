#!/usr/bin/env node

/**
 * People Counting Sensor Simulator
 *
 * This script simulates Axis sensors for the People Counting module by sending interval count data to the API endpoint.
 * It retrieves all sensors from the peoplecount_sensors table in the database and sends random count data for each sensor
 * at the specified interval.
 *
 * Usage:
 *   node scripts/simulate-sensors.js [options]
 *
 * Options:
 *   --interval=<seconds>   Interval between data sends in seconds (default: 60)
 *   --min-count=<number>   Minimum count value (default: 0)
 *   --max-count=<number>   Maximum count value (default: 10)
 *   --sensor-id=<id>       Simulate only the specified sensor ID (default: all sensors)
 *   --verbose              Show detailed output (default: concise output)
 *   --help                 Show this help message
 */

import { createRequire } from 'module';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';
import axios from 'axios';

// Setup require for CommonJS modules
const require = createRequire(import.meta.url);
const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

// Load environment variables
require('dotenv').config({ path: join(__dirname, '..', '.env') });

// Database configuration
const dbConfig = {
  client: process.env.DB_CONNECTION || 'sqlite',
  connection: process.env.DB_CONNECTION === 'sqlite'
    ? { filename: join(__dirname, '..', 'database', 'database.sqlite') }
    : {
        host: process.env.DB_HOST,
        port: process.env.DB_PORT,
        user: process.env.DB_USERNAME,
        password: process.env.DB_PASSWORD,
        database: process.env.DB_DATABASE,
      },
  useNullAsDefault: process.env.DB_CONNECTION === 'sqlite'
};

// Parse command line arguments
const args = process.argv.slice(2);
const options = {
  interval: 60,
  minCount: 0,
  maxCount: 10,
  sensorId: null,
  help: false,
  verbose: false
};

args.forEach(arg => {
  if (arg === '--help') {
    options.help = true;
  } else if (arg === '--verbose') {
    options.verbose = true;
  } else if (arg.startsWith('--interval=')) {
    options.interval = parseInt(arg.split('=')[1], 10);
  } else if (arg.startsWith('--min-count=')) {
    options.minCount = parseInt(arg.split('=')[1], 10);
  } else if (arg.startsWith('--max-count=')) {
    options.maxCount = parseInt(arg.split('=')[1], 10);
  } else if (arg.startsWith('--sensor-id=')) {
    options.sensorId = parseInt(arg.split('=')[1], 10);
  }
});

// Show help message if requested
if (options.help) {
  console.log(`
People Counting Sensor Simulator

This script simulates Axis sensors for the People Counting module by sending interval count data to the API endpoint.
It retrieves all sensors from the peoplecount_sensors table in the database and sends random count data for each sensor
at the specified interval.

Usage:
  node scripts/simulate-sensors.js [options]

Options:
  --interval=<seconds>   Interval between data sends in seconds (default: 60)
  --min-count=<number>   Minimum count value (default: 0)
  --max-count=<number>   Maximum count value (default: 10)
  --sensor-id=<id>       Simulate only the specified sensor ID (default: all sensors)
  --verbose              Show detailed output (default: concise output)
  --help                 Show this help message
  `);
  process.exit(0);
}

// Initialize database connection
const knex = require('knex')(dbConfig);

// Function to get all sensors from the database
async function getSensors() {
  try {
    let query = knex('peoplecount_sensors').select('*');

    if (options.sensorId) {
      query = query.where('id', options.sensorId);
    }

    return await query;
  } catch (error) {
    console.error('Error retrieving sensors:', error);
    return [];
  }
}

// Function to generate random count data
function generateRandomCount(min, max) {
  return Math.floor(Math.random() * (max - min + 1)) + min;
}

// Function to generate a timestamp
function generateTimestamp(offsetMinutes = 0) {
  const date = new Date();
  date.setMinutes(date.getMinutes() - offsetMinutes);
  return date.toISOString();
}

// Function to generate Axis sensor data
function generateAxisData(sensor) {
  const countIn = generateRandomCount(options.minCount, options.maxCount);
  const countOut = generateRandomCount(options.minCount, options.maxCount);
  const tsTo = generateTimestamp(0);
  const tsFrom = generateTimestamp(1); // 1 minute ago

  return {
    apiName: "Axis Retail Data",
    apiVersion: "0.4",
    sensor: {
      serial: sensor.serial
    },
    data: {
      measurements: [
        {
          kind: "people-counts",
          utcFrom: tsFrom,
          utcTo: tsTo,
          items: [
            {
              direction: "in",
              count: countIn
            },
            {
              direction: "out",
              count: countOut
            }
          ]
        }
      ]
    }
  };
}

// Function to send data to the API endpoint
async function sendData(sensor, data, stats) {
  try {
    const apiUrl = `${process.env.APP_URL}/api/peoplecount/interval-count`;

    const response = await axios.post(apiUrl, data, {
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Authorization': `Bearer ${sensor.api_token}`
      }
    });

    // Update statistics
    stats.processed++;
    stats.totalIn += data.data.measurements[0].items[0].count;
    stats.totalOut += data.data.measurements[0].items[1].count;

    if (response.status === 201) {
      stats.successful++;
    } else {
      stats.otherResponses++;
    }

    if (options.verbose) {
      console.log(`[${new Date().toISOString()}] Sent data for sensor ${sensor.id} (${sensor.serial}): in=${data.data.measurements[0].items[0].count}, out=${data.data.measurements[0].items[1].count}`);
      console.log(`Response: ${response.status} ${JSON.stringify(response.data)}`);
    }

    return response.status;
  } catch (error) {
    stats.failed++;
    console.error(`Error sending data for sensor ${sensor.id}:`, error.response?.data || error.message);
    return 'error';
  }
}

// Main function to simulate sensors
async function simulateSensors() {
  try {
    const sensors = await getSensors();

    if (sensors.length === 0) {
      console.log('No sensors found in the database.');
      return;
    }

    if (options.verbose) {
      console.log(`Found ${sensors.length} sensor(s) to simulate.`);
    }

    // Initialize statistics
    const stats = {
      total: sensors.length,
      processed: 0,
      successful: 0,
      failed: 0,
      otherResponses: 0,
      totalIn: 0,
      totalOut: 0,
      skipped: 0
    };

    // Process each sensor
    for (const sensor of sensors) {
      if (sensor.vendor === 'Axis') {
        const data = generateAxisData(sensor);
        await sendData(sensor, data, stats);
      } else {
        stats.skipped++;
        if (options.verbose) {
          console.log(`Skipping sensor ${sensor.id} with unsupported vendor: ${sensor.vendor}`);
        }
      }
    }

    // Display summary (only in non-verbose mode, as verbose mode already shows details)
    if (!options.verbose) {
      const timestamp = new Date().toISOString();
      console.log(`[${timestamp}] Simulation cycle completed: ${stats.processed}/${stats.total} sensors processed`);
      console.log(`  Success: ${stats.successful}, Failed: ${stats.failed}, Other: ${stats.otherResponses}, Skipped: ${stats.skipped}`);
      console.log(`  Total counts - In: ${stats.totalIn}, Out: ${stats.totalOut}`);
    }
  } catch (error) {
    console.error('Error simulating sensors:', error);
  }
}

// Run the simulation at the specified interval
console.log(`Starting sensor simulation with interval: ${options.interval} seconds`);
if (options.verbose) {
  console.log(`Count range: ${options.minCount} to ${options.maxCount}`);
  if (options.sensorId) {
    console.log(`Simulating only sensor ID: ${options.sensorId}`);
  } else {
    console.log('Simulating all sensors');
  }
  console.log(`Verbose mode: ${options.verbose ? 'enabled' : 'disabled'}`);
}

// Run immediately once
simulateSensors();

// Then set up the interval
setInterval(simulateSensors, options.interval * 1000);

// Handle graceful shutdown
process.on('SIGINT', async () => {
  console.log('Shutting down sensor simulation...');
  await knex.destroy();
  process.exit(0);
});
