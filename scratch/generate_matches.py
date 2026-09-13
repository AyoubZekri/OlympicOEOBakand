# -*- coding: utf-8 -*-
import os

matches_tsx = """import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { Plus, Edit2, Trash2 } from 'lucide-react';
import { Applink } from '../../../LinkApi';
import { AddMatchDialog } from './AddMatchDialog';

export const Matches = () => {
  const [matches, setMatches] = useState<any[]>([]);
  const [isDialogOpen, setIsDialogOpen] = useState(false);
  const [editingMatch, setEditingMatch] = useState<any>(null);

  const fetchMatches = async () => {
    try {
      const token = localStorage.getItem('token');
      const response = await axios.get(Applink.matches, {
        headers: { Authorization: `Bearer ${token}` }
      });
      if (response.data.status === 'success') {
        setMatches(response.data.data);
      }
    } catch (error) {
      console.error('Error fetching matches:', error);
    }
  };

  useEffect(() => {
    fetchMatches();
  }, []);

  const handleDelete = async (id: number) => {
    if (!window.confirm('هل أنت متأكد من حذف هذه المباراة؟')) return;
    try {
      const token = localStorage.getItem('token');
      await axios.post(Applink.deleteMatch, { id }, {
        headers: { Authorization: `Bearer ${token}` }
      });
      fetchMatches();
    } catch (error) {
      console.error('Error deleting match:', error);
    }
  };

  const openAddDialog = () => {
    setEditingMatch(null);
    setIsDialogOpen(true);
  };

  const openEditDialog = (match: any) => {
    setEditingMatch(match);
    setIsDialogOpen(true);
  };

  return (
    <div className="members-container">
      <div className="header-actions">
        <h2>المباريات</h2>
        <button className="add-btn" onClick={openAddDialog}>
          <Plus size={20} /> إضافة مباراة
        </button>
      </div>

      <div className="table-container">
        <table>
          <thead>
            <tr>
              <th>المنافسة</th>
              <th>عنوان المباراة</th>
              <th>الخصم</th>
              <th>تاريخ المباراة</th>
              <th>المكان</th>
              <th>المدرب</th>
              <th>المسؤول</th>
              <th>إجراءات</th>
            </tr>
          </thead>
          <tbody>
            {matches.map((match) => (
              <tr key={match.id}>
                <td>{match.competition}</td>
                <td>{match.match_title}</td>
                <td>{match.opponent}</td>
                <td>{match.match_date ? new Date(match.match_date).toLocaleString('ar-EG') : '-'}</td>
                <td>{match.location}</td>
                <td>{match.coach_id?.first_name} {match.coach_id?.last_name}</td>
                <td>{match.admin_id?.name}</td>
                <td>
                  <div className="action-buttons">
                    <button className="edit-btn" onClick={() => openEditDialog(match)}>
                      <Edit2 size={16} />
                    </button>
                    <button className="delete-btn" onClick={() => handleDelete(match.id)}>
                      <Trash2 size={16} />
                    </button>
                  </div>
                </td>
              </tr>
            ))}
            {matches.length === 0 && (
              <tr>
                <td colSpan={8} style={{ textAlign: 'center' }}>لا توجد مباريات</td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      <AddMatchDialog
        isOpen={isDialogOpen}
        onClose={() => setIsDialogOpen(false)}
        onSave={fetchMatches}
        matchData={editingMatch}
      />
    </div>
  );
};
"""

dialog_tsx = """import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { Applink } from '../../../LinkApi';
import { X } from 'lucide-react';

interface AddMatchDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onSave: () => void;
  matchData?: any;
}

export const AddMatchDialog: React.FC<AddMatchDialogProps> = ({ isOpen, onClose, onSave, matchData }) => {
  const [formData, setFormData] = useState({
    competition: '',
    opponent: '',
    match_title: '',
    match_date: '',
    location: '',
    gathering_time: '',
    gathering_location: '',
    coach_id: '',
    admin_id: ''
  });
  
  const [individuals, setIndividuals] = useState<any[]>([]);
  const [users, setUsers] = useState<any[]>([]);

  useEffect(() => {
    if (isOpen) {
      if (matchData) {
        setFormData({
          competition: matchData.competition || '',
          opponent: matchData.opponent || '',
          match_title: matchData.match_title || '',
          match_date: matchData.match_date ? matchData.match_date.substring(0, 16) : '',
          location: matchData.location || '',
          gathering_time: matchData.gathering_time ? matchData.gathering_time.substring(0, 16) : '',
          gathering_location: matchData.gathering_location || '',
          coach_id: matchData.coach_id?.id || matchData.coach_id || '',
          admin_id: matchData.admin_id?.id || matchData.admin_id || ''
        });
      } else {
        setFormData({
          competition: '', opponent: '', match_title: '', match_date: '',
          location: '', gathering_time: '', gathering_location: '',
          coach_id: '', admin_id: ''
        });
      }
      fetchDependencies();
    }
  }, [isOpen, matchData]);

  const fetchDependencies = async () => {
    try {
      const token = localStorage.getItem('token');
      const headers = { Authorization: `Bearer ${token}` };
      const [indRes, usersRes] = await Promise.all([
        axios.get(Applink.individuals, { headers }),
        axios.get(Applink.users, { headers })
      ]);
      if (indRes.data.status === 'success') {
        const allowedRoles = ['مدرب', 'مساعد مدرب', 'مدرب حراس', 'موظف', 'إداري', 'طبيب'];
        const filtered = indRes.data.data.filter((ind: any) => 
            ind.role?.name && allowedRoles.includes(ind.role.name.strip())
        );
        const validIndividuals = filtered.length > 0 ? filtered : indRes.data.data;
        setIndividuals(validIndividuals);
      }
      if (usersRes.data.status === 'success') {
        setUsers(usersRes.data.data);
      }
    } catch (error) {
      console.error('Error fetching dependencies', error);
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      const token = localStorage.getItem('token');
      const url = matchData ? Applink.updateMatch : Applink.createMatch;
      const payload = matchData ? { ...formData, id: matchData.id } : formData;
      
      const res = await axios.post(url, payload, {
        headers: { Authorization: `Bearer ${token}` }
      });
      if (res.data.status === 'success') {
        onSave();
        onClose();
      }
    } catch (error) {
      console.error('Error saving match:', error);
      alert('حدث خطأ أثناء الحفظ');
    }
  };

  if (!isOpen) return null;

  return (
    <div className="dialog-overlay">
      <div className="dialog-content">
        <div className="dialog-header">
          <h2>{matchData ? 'تعديل المباراة' : 'إضافة مباراة جديدة'}</h2>
          <button className="close-btn" onClick={onClose}><X size={24} /></button>
        </div>
        <form onSubmit={handleSubmit} className="dialog-form">
          <div className="form-group">
            <label>المنافسة</label>
            <input type="text" value={formData.competition} onChange={e => setFormData({...formData, competition: e.target.value})} required />
          </div>
          <div className="form-group">
            <label>عنوان المباراة</label>
            <input type="text" value={formData.match_title} onChange={e => setFormData({...formData, match_title: e.target.value})} required />
          </div>
          <div className="form-group">
            <label>الخصم</label>
            <input type="text" value={formData.opponent} onChange={e => setFormData({...formData, opponent: e.target.value})} required />
          </div>
          <div className="form-group">
            <label>تاريخ وتوقيت المباراة</label>
            <input type="datetime-local" value={formData.match_date} onChange={e => setFormData({...formData, match_date: e.target.value})} required />
          </div>
          <div className="form-group">
            <label>المكان</label>
            <input type="text" value={formData.location} onChange={e => setFormData({...formData, location: e.target.value})} required />
          </div>
          <div className="form-group">
            <label>موعد التجمع</label>
            <input type="datetime-local" value={formData.gathering_time} onChange={e => setFormData({...formData, gathering_time: e.target.value})} />
          </div>
          <div className="form-group">
            <label>مكان التجمع</label>
            <input type="text" value={formData.gathering_location} onChange={e => setFormData({...formData, gathering_location: e.target.value})} />
          </div>
          <div className="form-group">
            <label>المدرب / الإداري (فريق العمل)</label>
            <select value={formData.coach_id} onChange={e => setFormData({...formData, coach_id: e.target.value})} required>
              <option value="">اختر المدرب/الموظف</option>
              {individuals.map(ind => (
                <option key={ind.id} value={ind.id}>{ind.first_name} {ind.last_name} {ind.role ? `(${ind.role.name})` : ''}</option>
              ))}
            </select>
          </div>
          <div className="form-group">
            <label>المسؤول المضاف</label>
            <select value={formData.admin_id} onChange={e => setFormData({...formData, admin_id: e.target.value})} required>
              <option value="">اختر المسؤول</option>
              {users.map(u => (
                <option key={u.id} value={u.id}>{u.name}</option>
              ))}
            </select>
          </div>
          <div className="dialog-actions">
            <button type="button" onClick={onClose} className="cancel-btn">إلغاء</button>
            <button type="submit" className="submit-btn">حفظ</button>
          </div>
        </form>
      </div>
    </div>
  );
};
"""

with open('src/View/Screen/Matches/Matches.tsx', 'w', encoding='utf-8') as f:
    f.write(matches_tsx)

with open('src/View/Screen/Matches/AddMatchDialog.tsx', 'w', encoding='utf-8') as f:
    f.write(dialog_tsx)
