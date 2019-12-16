/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

package redis

import (
	"errors"
	"github.com/mediocregopher/radix/v3"
	"reflect"
	"testing"
	"zabbix.com/pkg/plugin"
)

const (
	infoCommonSectionOutput = `# CommonSection
foo:123
bar:0.00`

	infoDefaultSectionOutput = `# DefaultSection
test:111`

	infoExtendedSectionOutput = `
# Commandstats
cmdstat_info:calls=11150,usec=823882,usec_per_call=73.89
cmdstat_config:calls=10,usec=383,usec_per_call=38.30`

	infoMasterReplicationOutput = `# Replication
role:master
connected_slaves:1
slave0:ip=172.18.0.2,port=6379,state=online,offset=953099,lag=1
master_replid:5a9346f8855b4766efca35d4a83cfd151db3fa4a`

	infoSlaveReplicationOutput = `# Replication
role:slave
master_host:redis-master
master_port:6379
slave_repl_offset:953057
connected_slaves:0`

	infoMalformedSectionOutput = `# 
test:111`
)

func Test_parseRedisInfo(t *testing.T) {
	type args struct {
		info string
	}

	tests := []struct {
		name    string
		args    args
		wantRes redisInfo
		wantErr bool
	}{
		{
			"Should fail on malformed input",
			args{"foobar"},
			nil,
			true,
		},
		{
			"Should fail on empty section name",
			args{infoMalformedSectionOutput},
			nil,
			true,
		},
		{
			"Should fail on empty input",
			args{""},
			nil,
			true,
		},
		{
			`Parse of output of "info CommonSection" command`,
			args{infoCommonSectionOutput},
			redisInfo{
				"CommonSection": infoKeySpace{
					"foo": "123", "bar": "0.00",
				},
			},
			false,
		},
		{
			`Parse of output of "info Commandstats" command`,
			args{infoExtendedSectionOutput},
			redisInfo{
				"Commandstats": infoKeySpace{
					"cmdstat_info": infoExtKeySpace{
						"calls":         "11150",
						"usec":          "823882",
						"usec_per_call": "73.89",
					},
					"cmdstat_config": infoExtKeySpace{
						"calls":         "10",
						"usec":          "383",
						"usec_per_call": "38.30",
					},
				},
			},
			false,
		},
		{
			`Parse of output of "info Replication" command for Master role`,
			args{infoMasterReplicationOutput},
			redisInfo{
				"Replication": infoKeySpace{
					"role":             "master",
					"connected_slaves": "1",
					"slave0": infoExtKeySpace{
						"ip":     "172.18.0.2",
						"port":   "6379",
						"state":  "online",
						"offset": "953099",
						"lag":    "1",
					},
					"master_replid": "5a9346f8855b4766efca35d4a83cfd151db3fa4a",
				},
			},
			false,
		},
		{
			`Parse of output of "info Replication" command for Slave role`,
			args{infoSlaveReplicationOutput},
			redisInfo{
				"Replication": infoKeySpace{
					"role":              "slave",
					"master_host":       "redis-master",
					"master_port":       "6379",
					"slave_repl_offset": "953057",
					"connected_slaves":  "0",
				},
			},
			false,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			gotRes, err := parseRedisInfo(tt.args.info)
			if (err != nil) != tt.wantErr {
				t.Errorf("parseRedisInfo() error = %#v, wantErr %#v", err, tt.wantErr)
				return
			}
			if !reflect.DeepEqual(gotRes, tt.wantRes) {
				t.Errorf("parseRedisInfo() = %#v, want %#v", gotRes, tt.wantRes)
			}
		})
	}
}

func Benchmark_parseRedisInfo_Common(b *testing.B) {
	for i := 0; i < b.N; i++ {
		_, _ = parseRedisInfo(infoExtendedSectionOutput)
	}
}

func Benchmark_parseRedisInfo_Extended(b *testing.B) {
	for i := 0; i < b.N; i++ {
		_, _ = parseRedisInfo(infoCommonSectionOutput)
	}
}

func TestPlugin_infoHandler(t *testing.T) {
	impl.Configure(&plugin.GlobalOptions{}, nil)

	stubConn := radix.Stub("", "", func(args []string) interface{} {
		switch args[1] {
		case "commonsection":
			return infoCommonSectionOutput

		case "default":
			return infoDefaultSectionOutput

		case "unknownsection":
			return ""

		default:
			return errors.New("cannot fetch data")
		}
	})

	defer stubConn.Close()

	conn := &redisConnStub{
		client: stubConn,
	}

	type args struct {
		conn   redisClient
		params []string
	}
	tests := []struct {
		name    string
		p       *Plugin
		args    args
		want    interface{}
		wantErr bool
	}{
		{
			"Default section should be used if it is not explicitly specified",
			&impl,
			args{conn: conn, params: []string{"", ""}},
			`{"DefaultSection":{"test":"111"}}`,
			false,
		},
		{
			"Should fetch specified section and return marshalled result",
			&impl,
			args{conn: conn, params: []string{"", "COMMONSECTION"}},
			`{"CommonSection":{"bar":"0.00","foo":"123"}}`,
			false,
		},
		{
			"Should fail if error occurred",
			&impl,
			args{conn: conn, params: []string{"", "WantErr"}},
			nil,
			true,
		},
		{
			"Should fail on malformed data",
			&impl,
			args{conn: conn, params: []string{"", "UnknownSection"}},
			nil,
			true,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got, err := tt.p.infoHandler(tt.args.conn, tt.args.params)
			if (err != nil) != tt.wantErr {
				t.Errorf("Plugin.infoHandler() error = %v, wantErr %v", err, tt.wantErr)
				return
			}
			if !reflect.DeepEqual(got, tt.want) {
				t.Errorf("Plugin.infoHandler() = %v, want %v", got, tt.want)
			}
		})
	}
}
