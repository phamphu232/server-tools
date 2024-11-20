<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Tools</title>
    <script src="https://cdn.jsdelivr.net/npm/vue@3"></script>
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 5px;
            text-align: left;
        }

        th {
            background-color: #f4f4f4;
        }

        .think {
            color: #888;
            font-weight: normal;
        }

        .red {
            color: red;
        }

        .blue {
            color: blue;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .nowrap {
            white-space: nowrap;
        }

        .warning {
            color: #888;
        }

        .tooltip {
            position: relative;
            cursor: pointer;
            text-decoration: 1px underline dotted;
        }

        .tooltip::after {
            content: attr(data-detail);
            white-space: pre-wrap;
            /* Preserve whitespace */
            word-wrap: break-word;
            /* Break long words to prevent overflow */
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            background-color: rgba(0, 0, 0, 0.86);
            color: white;
            padding: 10px;
            border-radius: 5px;
            width: max-content;
            max-width: 1200px;
            font-family: monospace;
            /* Optional: Use monospace font to mimic <pre> tag */
            visibility: hidden;
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 1;
        }

        .tooltip:hover::after {
            visibility: visible;
            opacity: 1;
        }

        [v-cloak] {
            display: none;
        }

        .border-none {
            border: none;
        }

        .w-100 {
            width: 100%;
        }

        .w-50px {
            width: 50px;
        }

        .w-30px {
            width: 30px;
        }
    </style>
</head>

<body>
    <div id="app">
        <h1>Server Monitor</h1>
        <div style="text-align: right; font-weight: bold; margin-bottom: 5px;">
            <label><input type="checkbox" value="1" id="auto_refresh" checked="checked">Auto Refresh</label>
        </div>
        <table v-if="servers.length" v-cloak>
            <thead>
                <tr>
                    <th>No</th>
                    <th>PERSON IN CHARGE <span class="think">(Skype)</span></th>
                    <th>SERVER NAME</th>
                    <th>PLATFORM</th>
                    <th>PUBLIC_IP</th>
                    <th colspan="2">CPU <span class="think">| Throttle</span></th>
                    <th colspan="2">RAM <span class="think">| Throttle</th>
                    <th colspan="2">DISK <span class="think">| Throttle</th>
                    <th>UPDATE_AT</th>
                    <th>DELETE</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(server, index) in servers">
                    <td>{{ index + 1 }}</td>
                    <td>
                        <input type="text" :data-key="server.KEY" name="person_in_charge"
                            class="config border-none w-100" :value="server.PERSON_IN_CHARGE" autocomplete="off"
                            placeholder="id1|name1,id2|name2" @input="updateConfig($event.target)" />
                    </td>
                    <td>
                        <input type="text" :data-key="server.KEY" name="server_name" class="config border-none w-100"
                            :value="server.SERVER_NAME" autocomplete="off" placeholder=""
                            @input="updateConfig($event.target)" />
                    </td>
                    <td><a :href="server.CLOUD_LINK" target="_blank" class="blue">{{ server.PLATFORM }}</a></td>
                    <td>{{ server.PUBLIC_IP }}</td>
                    <td :class="parseInt(server.CPU.usage_percent) > parseInt(server.CPU_THROTTLE) ? 'tooltip red' : 'tooltip'"
                        :data-detail="server.CPU_TOP">{{ server.CPU.usage_percent }}%</td>
                    <td class="w-50px think nowrap">
                        <input type="text" :data-key="server.KEY" name="cpu_throttle"
                            class="config warning text-right w-30px border-none" :value="server.CPU_THROTTLE"
                            pattern="[0-9]*" autocomplete="off" @input="updateConfig($event.target)" />%
                    </td>
                    <td :class="parseInt(server.RAM.usage_percent) > parseInt(server.RAM_THROTTLE) ? 'tooltip red' : 'tooltip'"
                        :data-detail="server.RAM_TOP">
                        {{ server.RAM.usage_percent }}% ~
                        {{convertKBtoGB(server['RAM']['used'])}}GB / {{convertKBtoGB(server['RAM']['total'])}}GB
                    </td>
                    <td class="w-50px think nowrap">
                        <input type="text" :data-key="server.KEY" name="ram_throttle"
                            class="config warning text-right w-30px border-none" :value="server.RAM_THROTTLE"
                            pattern="[0-9]*" autocomplete="off" @input="updateConfig($event.target)" />%
                    </td>
                    <td :class="parseInt(server.DISK.usage_percent) > parseInt(server.DISK_THROTTLE) ? 'red' : ''">
                        {{ server.DISK.usage_percent}}% ~ {{convertKBtoGB(server['DISK']['used'])}}GB /
                        {{convertKBtoGB(server['DISK']['total'])}}GB
                    </td>
                    <td class="w-50px think nowrap">
                        <input type="text" :data-key="server.KEY" name="disk_throttle"
                            class="config warning text-right w-30px border-none" :value="server.DISK_THROTTLE"
                            pattern="[0-9]*" autocomplete="off" @input="updateConfig($event.target)" />%
                    </td>
                    <td>{{ convertTimestampToDateTime(`${server.TIMESTAMP}000`) }}</td>
                    <td class="text-center"><button @click="deleteRecord(server.KEY)">Delete</button></td>
                </tr>
            </tbody>
        </table>

        <div style="margin-top: 8px;">
            <b>Notes:</b><br />
            <i> - CPU, RAM: Warning if exceeding throttle for 5 minutes.</i><br />
            <i> - DISK: Warning if exceeding throttle every 2 hours.</i><br />
            <i> - MISSING REPORT: Warning every 15 minutes.</i><br />
            <i> - DISABLE WARNING: Set throttle to 0.</i>
        </div>
    </div>

    <script>
        // Vue.js Application
        const app = Vue.createApp({
            data() {
                return {
                    servers: [], // To store file data
                    debounceTimeout: null,
                };
            },
            methods: {
                async fetchFiles() {
                    try {
                        const response = await fetch('./list.php'); // Fetch API call
                        if (!response.ok) {
                            throw new Error(`HTTP error! Status: ${response.status}`);
                        }
                        const data = await response.json(); // Parse JSON response
                        this.servers = data.Data; // Assuming the response is an array of objects
                    } catch (error) {
                        console.error("Error fetching files:", error);
                    }
                },

                convertKBtoGB(kilobytes) {
                    const gigabytes = kilobytes / (1024 * 1024);
                    return gigabytes.toFixed(2); // Formats to 2 decimal places
                },

                convertTimestampToDateTime(timestamp) {
                    timestamp = parseInt(timestamp);
                    const date = new Date(timestamp); // Create a Date object
                    const year = date.getFullYear();
                    const month = String(date.getMonth() + 1).padStart(2, '0'); // Months are zero-based
                    const day = String(date.getDate()).padStart(2, '0');
                    const hours = String(date.getHours()).padStart(2, '0');
                    const minutes = String(date.getMinutes()).padStart(2, '0');
                    const seconds = String(date.getSeconds()).padStart(2, '0');

                    return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`; // Format as YYYY-MM-DD HH:MM:SS
                },

                updateConfig(element) {
                    clearTimeout(this.debounceTimeout); // Clear the previous timeout if it exists

                    // Set a new timeout to delay the POST request
                    this.debounceTimeout = setTimeout(() => {
                        const key = element.dataset.key;

                        fetch('./update.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({
                                    key: key,
                                    person_in_charge: document.querySelector(`[data-key="${key}"][name=person_in_charge]`).value,
                                    server_name: document.querySelector(`[data-key="${key}"][name=server_name]`).value,
                                    cpu_throttle: document.querySelector(`[data-key="${key}"][name=cpu_throttle]`).value,
                                    ram_throttle: document.querySelector(`[data-key="${key}"][name=ram_throttle]`).value,
                                    disk_throttle: document.querySelector(`[data-key="${key}"][name=disk_throttle]`).value,
                                    action: "update"
                                })
                            })
                            .then(response => response.text())
                            .then(data => {
                                console.log('Response:', data);
                                this.fetchFiles();
                            })
                            .catch(error => {
                                console.error('Error:', error);
                            });
                    }, 500);
                },

                deleteRecord(key) {
                    if (confirm("Are you sure you want to delete this record ?")) {
                        fetch('./delete.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({
                                    key: key,
                                    action: "delete"
                                })
                            })
                            .then(response => response.text())
                            .then(data => {
                                console.log('Response:', data);
                                this.fetchFiles();
                            })
                            .catch(error => {
                                console.error('Error:', error);
                            });
                    }
                },

                autoRefreshPage() {
                    const checkbox = document.getElementById("auto_refresh");

                    reloadPageEveryMinute = () => {
                        setInterval(() => {
                            if (checkbox.checked) {
                                this.fetchFiles();
                            }
                        }, 20000); // 60000 milliseconds = 1 minute
                    }

                    checkbox.addEventListener("change", () => {
                        if (checkbox.checked) {
                            reloadPageEveryMinute();
                        }
                    });

                    // Start reloading automatically if checkbox is checked by default
                    if (checkbox.checked) {
                        reloadPageEveryMinute();
                    }
                }
            },
            mounted() {
                this.fetchFiles();

                this.autoRefreshPage();
            },
        });

        app.mount("#app");
    </script>
</body>

</html>